/**
 * External dependencies
 */
import type { Browser, BrowserContext, Page } from '@playwright/test';
import qit from '@qit/helpers';

/**
 * Internal dependencies
 */
import { test, expect, getAuthState } from '../../../fixtures/auth';
import * as merchant from '../../../utils/merchant';
import * as navigation from '../../../utils/shopper-navigation';
import { getAnonymousShopper } from '../../../utils/helpers';

/**
 * Intercepts the public config REST endpoint and aborts the request,
 * simulating a network failure.
 */
const interceptConfigEndpointWithFailure = async ( page: Page ) => {
	await page.route(
		'**/wc/v3/payments/multi-currency/public/config',
		( route ) => route.abort( 'failed' )
	);
};

/**
 * Reads a WP option value, returning empty string if the option does not exist.
 */
const getOption = async ( name: string ): Promise< string > => {
	try {
		return ( await qit.wp( `option get ${ name }`, true ) ).stdout.trim();
	} catch {
		return '';
	}
};

/**
 * Restores a WP option to its original value, or deletes it if it didn't exist.
 */
const restoreOption = async (
	name: string,
	original: string
): Promise< void > => {
	if ( original ) {
		await qit.wp( `option update ${ name } ${ original }` );
	} else {
		await qit.wp( `option delete ${ name }` );
	}
};

/**
 * Ensures at least one sale product exists in WooCommerce.
 *
 * QIT's default dataset may not include sale products, which the
 * screen-reader test needs for [data-wcpay-sr-type] elements.
 */
const ensureSaleProductExists = async (): Promise< void > => {
	const { stdout } = await qit.wp(
		'wc product list --on_sale=true --status=publish --format=count --user=1',
		true
	);
	const saleCount = parseInt( stdout.trim(), 10 );
	if ( saleCount > 0 ) {
		return;
	}

	// Find any published simple product and add a sale price.
	const { stdout: productIds } = await qit.wp(
		'wc product list --type=simple --status=publish --format=ids --user=1',
		true
	);
	const firstId = productIds.trim().split( /\s+/ )[ 0 ];
	if ( firstId ) {
		await qit.wp(
			`wc product update ${ firstId } --sale_price=5.00 --user=1`
		);
	}
};

/**
 * Navigates to the shop page and waits for the async price renderer to convert
 * skeleton prices. Includes a diagnostic pre-flight check to provide actionable
 * error messages if the renderer infrastructure is missing.
 */
const goToShopAndWaitForConversion = async (
	page: Page,
	{ timeout = 20000 }: { timeout?: number } = {}
) => {
	await navigation.goToShop( page );

	// Pre-flight: verify skeleton markup exists before waiting for conversion.
	// If the PHP-side async renderer didn't fire, the skeletons won't be in
	// the DOM and waiting for conversion would always time out.
	const skeletonCount = await page.locator( '[data-wcpay-price]' ).count();

	if ( skeletonCount === 0 ) {
		// Capture diagnostic data for debugging.
		const configDefined = await page.evaluate(
			() => typeof ( window as any ).wcpayAsyncPriceConfig !== 'undefined'
		);
		const html = await page.content();
		const hasAsyncClass = html.includes( 'wcpay-async-price' );

		throw new Error(
			'Async renderer pre-flight failed: no [data-wcpay-price] elements found on the shop page. ' +
				`Diagnostics: wcpayAsyncPriceConfig defined=${ configDefined }, ` +
				`wcpay-async-price class in HTML=${ hasAsyncClass }, ` +
				`skeleton count=${ skeletonCount }. ` +
				'The PHP async renderer hooks may not have fired — check that ' +
				'is_cache_optimized_mode() returns true and there is no active WC session.'
		);
	}

	// Verify the JS bundle loaded correctly. The CI build can produce a
	// truncated file (observed: 615 bytes vs expected ~21KB) that contains
	// the webpack runtime but not the actual renderer code. If the bundle
	// is broken, the renderer IIFE crashes silently and no conversion occurs.
	const bundleCheck = await page.evaluate( () => {
		const win = window as any;
		const scriptEl = document.getElementById(
			'wcpay-multi-currency-async-renderer-js'
		) as HTMLScriptElement | null;
		return {
			scriptExists: !! scriptEl,
			scriptSrc: scriptEl?.src || '',
			// The renderer sets a 10s timeout inside init(). If neither
			// converted nor error elements appear, the IIFE likely crashed.
			configKeys: win.wcpayAsyncPriceConfig
				? Object.keys( win.wcpayAsyncPriceConfig )
				: [],
			hasDefaultCurrency: !! win.wcpayAsyncPriceConfig?.defaultCurrency,
			wcpayAssetsDefined: typeof win.wcpayAssets !== 'undefined',
			jsErrors: ( win.__wcpayJsErrors || [] ).slice( 0, 5 ),
		};
	} );

	// If the JS bundle is broken, fail fast with a clear message.
	if ( bundleCheck.jsErrors.length > 0 || ! bundleCheck.wcpayAssetsDefined ) {
		throw new Error(
			'Async renderer JS bundle appears broken — the IIFE likely crashed during initialization. ' +
				`Diagnostics: ${ JSON.stringify( bundleCheck ) }. ` +
				'This is typically caused by a truncated dist/multi-currency-async-renderer.js ' +
				'in the build artifact (e.g. 615 bytes instead of ~21KB).'
		);
	}

	// Wait for the JS renderer to convert at least one price.
	const convertedPrice = page.locator(
		'[data-wcpay-price].wcpay-price-converted'
	);

	try {
		await expect( convertedPrice.first() ).toBeVisible( { timeout } );
	} catch ( error ) {
		// Capture JS-side diagnostics on failure.
		const diagnostics = await page.evaluate( () => {
			const win = window as any;
			const scriptEl = document.getElementById(
				'wcpay-multi-currency-async-renderer-js'
			) as HTMLScriptElement | null;
			// Use Performance API to check the actual size of the loaded JS file.
			const perfEntries = performance.getEntriesByType(
				'resource'
			) as PerformanceResourceTiming[];
			const rendererEntry = perfEntries.find( ( e ) =>
				e.name.includes( 'async-renderer.js' )
			);
			return {
				configDefined: typeof win.wcpayAsyncPriceConfig !== 'undefined',
				configKeys: win.wcpayAsyncPriceConfig
					? Object.keys( win.wcpayAsyncPriceConfig )
					: [],
				hasDefaultCurrency: !! win.wcpayAsyncPriceConfig
					?.defaultCurrency,
				wcpayAssetsDefined: typeof win.wcpayAssets !== 'undefined',
				scriptSrc: scriptEl?.src || '',
				jsBundleTransferSize: rendererEntry?.transferSize ?? -1,
				jsBundleDecodedSize: rendererEntry?.decodedBodySize ?? -1,
				skeletons: document.querySelectorAll( '[data-wcpay-price]' )
					.length,
				converted: document.querySelectorAll( '.wcpay-price-converted' )
					.length,
				errors: document.querySelectorAll( '.wcpay-price-error' )
					.length,
				jsErrors: ( win.__wcpayJsErrors || [] ).slice( 0, 5 ),
			};
		} );

		throw new Error(
			`Async price conversion did not complete within ${ timeout }ms. ` +
				`JS diagnostics: ${ JSON.stringify( diagnostics ) }. ` +
				`Original error: ${ ( error as Error ).message }`
		);
	}
};

/**
 * Creates a fresh anonymous shopper context with sessionStorage pre-cleared
 * to prevent cached config from interfering with the async renderer.
 */
const getCleanAnonymousShopper = async ( browser: Browser ) => {
	const { shopperPage, shopperContext } = await getAnonymousShopper(
		browser
	);

	// Clear session cache and install a global error handler to capture
	// any JS errors that prevent the async renderer from executing.
	await shopperPage.addInitScript( () => {
		sessionStorage.removeItem( 'wcpay_mc_async_config' );
		( window as any ).__wcpayJsErrors = [];
		window.addEventListener( 'error', ( e ) => {
			( window as any ).__wcpayJsErrors.push(
				`${ e.message } at ${ e.filename }:${ e.lineno }`
			);
		} );
	} );

	return { shopperPage, shopperContext };
};

/**
 * Minimum expected file size (in bytes) for the async renderer JS bundle.
 * The production build produces ~21 KB. A value below this threshold indicates
 * a truncated build artifact (e.g. only the webpack runtime / public-path.js).
 */
const minBundleSizeBytes = 5000;

test.describe(
	'Multi-currency async price renderer',
	{ tag: '@shopper' },
	() => {
		let merchantContext: BrowserContext;
		let merchantPage: Page;
		let wasMulticurrencyEnabled = false;
		let originalEnabledCurrencies: string[] = [];
		let originalRenderingMode: string;
		let originalFeatureFlag: string;
		let originalAutoSwitch: string;
		let defaultCurrencySymbol: string;
		let bundleSizeBytes = 0;

		test.beforeAll( async ( { browser } ) => {
			test.setTimeout( 120000 );

			// Verify the JS bundle is not truncated. QIT may serve a stale
			// or incomplete build artifact (observed: 615 bytes vs ~21 KB).
			// When the bundle is broken, all JS-dependent tests will fail,
			// so we check upfront and skip with a clear message.
			const { stdout: sizeStr } = await qit.wp(
				`eval "echo filesize( WP_PLUGIN_DIR . '/woocommerce-payments/dist/multi-currency-async-renderer.js' );"`,
				true
			);
			bundleSizeBytes = parseInt( sizeStr.trim(), 10 ) || 0;

			merchantContext = await browser.newContext( {
				storageState: await getAuthState( browser, 'admin' ),
			} );
			merchantPage = await merchantContext.newPage();

			// Save original state for cleanup.
			originalEnabledCurrencies = await merchant.getEnabledCurrenciesSnapshot(
				merchantPage
			);
			wasMulticurrencyEnabled = await merchant.activateMulticurrency(
				merchantPage
			);

			// Snapshot current WP option values before modifying them.
			originalRenderingMode = await getOption(
				'wcpay_multi_currency_rendering_mode'
			);
			originalFeatureFlag = await getOption(
				'_wcpay_feature_mc_cache_optimized'
			);
			originalAutoSwitch = await getOption(
				'wcpay_multi_currency_enable_auto_currency'
			);

			// Read the store's default currency symbol via WooCommerce.
			defaultCurrencySymbol = (
				await qit.wp(
					'eval "echo html_entity_decode( get_woocommerce_currency_symbol() );"',
					true
				)
			 ).stdout.trim();

			// Add EUR as an enabled currency.
			await merchant.addCurrency( merchantPage, 'EUR' );

			// Ensure at least one sale product exists for screen-reader tests.
			await ensureSaleProductExists();

			// Enable cache-optimized mode via WP options.
			await qit.wp( 'option update _wcpay_feature_mc_cache_optimized 1' );
			await qit.wp(
				'option update wcpay_multi_currency_rendering_mode cache'
			);
			await qit.wp(
				'option update wcpay_multi_currency_enable_auto_currency yes'
			);

			// Flush the object cache so the web server sees the updated
			// options immediately. QIT may use a persistent object cache
			// (Redis) where WP-CLI option updates aren't visible to PHP-FPM
			// until the cache is invalidated.
			await qit.wp( 'cache flush' );
		} );

		test.afterAll( async () => {
			// Restore original WP option values (delete if they didn't exist).
			await restoreOption(
				'_wcpay_feature_mc_cache_optimized',
				originalFeatureFlag
			);
			await restoreOption(
				'wcpay_multi_currency_rendering_mode',
				originalRenderingMode
			);
			await restoreOption(
				'wcpay_multi_currency_enable_auto_currency',
				originalAutoSwitch
			);

			// Flush cache again so restored values are immediately visible.
			await qit.wp( 'cache flush' );

			await merchant.restoreCurrencies(
				merchantPage,
				originalEnabledCurrencies
			);
			if ( ! wasMulticurrencyEnabled ) {
				await merchant.deactivateMulticurrency( merchantPage );
			}

			await merchantContext?.close();
		} );

		test( 'should render skeleton markup and convert prices client-side', async ( {
			browser,
		} ) => {
			test.skip(
				bundleSizeBytes < minBundleSizeBytes,
				`JS bundle is truncated (${ bundleSizeBytes } bytes, expected >=${ minBundleSizeBytes }). ` +
					'The build artifact may be stale in QIT — see WOOPMNT-5992.'
			);

			const {
				shopperPage,
				shopperContext,
			} = await getCleanAnonymousShopper( browser );

			try {
				await goToShopAndWaitForConversion( shopperPage );

				// Skeleton placeholders should be removed after conversion.
				await expect(
					shopperPage.locator( '.wcpay-price-skeleton' )
				).toHaveCount( 0 );

				// The converted price should contain a currency symbol.
				const convertedPrice = shopperPage.locator(
					'[data-wcpay-price].wcpay-price-converted'
				);
				const priceText = await convertedPrice.first().textContent();
				expect( priceText ).toMatch( /[\$€£¥]|USD|EUR/ );
			} finally {
				await shopperContext?.close();
			}
		} );

		test( 'should convert screen-reader text alongside prices', async ( {
			browser,
		} ) => {
			test.skip(
				bundleSizeBytes < minBundleSizeBytes,
				`JS bundle is truncated (${ bundleSizeBytes } bytes, expected >=${ minBundleSizeBytes }). ` +
					'The build artifact may be stale in QIT — see WOOPMNT-5992.'
			);

			const {
				shopperPage,
				shopperContext,
			} = await getCleanAnonymousShopper( browser );

			try {
				await goToShopAndWaitForConversion( shopperPage );

				// The shop page should have sale or variable products with
				// screen-reader text annotations. Assert they exist and
				// were converted — a count of 0 would mean the test is
				// not exercising the feature.
				const srConverted = shopperPage.locator(
					'[data-wcpay-sr-type].wcpay-sr-converted'
				);
				const srCount = await srConverted.count();
				expect( srCount ).toBeGreaterThan( 0 );

				// Screen-reader text should contain a formatted price
				// with a currency symbol.
				const srText = await srConverted.first().textContent();
				expect( srText ).toMatch( /[\$€£¥]|USD|EUR/ );
			} finally {
				await shopperContext?.close();
			}
		} );

		test( 'should show fallback on network failure', async ( {
			browser,
		} ) => {
			test.skip(
				bundleSizeBytes < minBundleSizeBytes,
				`JS bundle is truncated (${ bundleSizeBytes } bytes, expected >=${ minBundleSizeBytes }). ` +
					'The build artifact may be stale in QIT — see WOOPMNT-5992.'
			);

			const {
				shopperPage,
				shopperContext,
			} = await getCleanAnonymousShopper( browser );

			try {
				// Abort the config fetch to simulate a network error.
				await interceptConfigEndpointWithFailure( shopperPage );

				await navigation.goToShop( shopperPage );

				// When the fetch fails, the renderer falls back to showing
				// prices in the default currency (from wcpayAsyncPriceConfig.defaultCurrency).
				// It still removes skeletons and marks prices as converted.
				const convertedPrice = shopperPage.locator(
					'[data-wcpay-price].wcpay-price-converted'
				);
				await expect( convertedPrice.first() ).toBeVisible( {
					timeout: 20000,
				} );

				// Skeleton placeholders should be removed.
				await expect(
					shopperPage.locator( '.wcpay-price-skeleton' )
				).toHaveCount( 0 );

				// Fallback prices should be in the store's default currency.
				const priceText = await convertedPrice.first().textContent();
				expect( priceText ).toContain( defaultCurrencySymbol );
			} finally {
				await shopperContext?.close();
			}
		} );

		test( 'should use server-side rendering when currency is set via URL', async ( {
			browser,
		} ) => {
			const { shopperPage, shopperContext } = await getAnonymousShopper(
				browser
			);

			try {
				// ?currency=EUR creates a WC session and triggers
				// server-side conversion instead of the async renderer.
				await navigation.goToShop( shopperPage, {
					currency: 'EUR',
				} );

				// Server-side rendered prices should NOT have the async
				// renderer skeleton classes.
				await expect(
					shopperPage.locator( '.wcpay-async-price' )
				).toHaveCount( 0 );

				// Prices should be in EUR (server-side converted).
				const priceAmount = shopperPage
					.locator( '.woocommerce-Price-amount' )
					.first();
				await expect( priceAmount ).toBeVisible();
				const priceText = await priceAmount.textContent();
				expect( priceText ).toContain( '€' );
			} finally {
				await shopperContext?.close();
			}
		} );
	}
);
