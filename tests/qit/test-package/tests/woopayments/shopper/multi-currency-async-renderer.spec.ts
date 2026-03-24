/**
 * External dependencies
 */
import type { BrowserContext, Page } from '@playwright/test';
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

		test.beforeAll( async ( { browser } ) => {
			test.setTimeout( 90000 );

			merchantContext = await browser.newContext( {
				storageState: await getAuthState( browser, 'admin' ),
			} );
			merchantPage = await merchantContext.newPage();

			// Save original state for cleanup.
			originalEnabledCurrencies =
				await merchant.getEnabledCurrenciesSnapshot( merchantPage );
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

			// Enable cache-optimized mode via WP options.
			await qit.wp(
				'option update _wcpay_feature_mc_cache_optimized 1'
			);
			await qit.wp(
				'option update wcpay_multi_currency_rendering_mode cache'
			);
			await qit.wp(
				'option update wcpay_multi_currency_enable_auto_currency yes'
			);
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

			await merchant.restoreCurrencies(
				merchantPage,
				originalEnabledCurrencies
			);
			if ( ! wasMulticurrencyEnabled ) {
				await merchant.deactivateMulticurrency( merchantPage );
			}

			await merchantContext?.close();
		} );

		// TODO: Investigate QIT environment incompatibility — async renderer
		// hooks don't fire in QIT despite correct WP option values.
		// See: WOOPMNT-5992
		test.skip( 'should render skeleton markup and convert prices client-side', async ( {
			browser,
		} ) => {
			const { shopperPage, shopperContext } =
				await getAnonymousShopper( browser );

			try {
				// Clear sessionStorage to prevent cached config from a prior run.
				await shopperPage.addInitScript( () => {
					sessionStorage.removeItem( 'wcpay_mc_async_config' );
				} );

				await navigation.goToShop( shopperPage );

				// The async renderer JS fetches the real config endpoint and
				// converts skeleton prices. Wait for at least one conversion.
				const convertedPrice = shopperPage.locator(
					'[data-wcpay-price].wcpay-price-converted'
				);
				await expect( convertedPrice.first() ).toBeVisible( {
					timeout: 15000,
				} );

				// Skeleton placeholders should be removed after conversion.
				await expect(
					shopperPage.locator( '.wcpay-price-skeleton' )
				).toHaveCount( 0 );

				// The converted price should contain a currency symbol.
				const priceText = await convertedPrice
					.first()
					.textContent();
				expect( priceText ).toMatch( /[\$€£¥]|USD|EUR/ );
			} finally {
				await shopperContext?.close();
			}
		} );

		test.skip( 'should convert screen-reader text alongside prices', async ( {
			browser,
		} ) => {
			const { shopperPage, shopperContext } =
				await getAnonymousShopper( browser );

			try {
				await shopperPage.addInitScript( () => {
					sessionStorage.removeItem( 'wcpay_mc_async_config' );
				} );

				await navigation.goToShop( shopperPage );

				// Wait for price conversion to complete.
				await expect(
					shopperPage
						.locator(
							'[data-wcpay-price].wcpay-price-converted'
						)
						.first()
				).toBeVisible( { timeout: 15000 } );

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

		test.skip( 'should show fallback on network failure', async ( {
			browser,
		} ) => {
			const { shopperPage, shopperContext } =
				await getAnonymousShopper( browser );

			try {
				await shopperPage.addInitScript( () => {
					sessionStorage.removeItem( 'wcpay_mc_async_config' );
				} );

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
					timeout: 15000,
				} );

				// Skeleton placeholders should be removed.
				await expect(
					shopperPage.locator( '.wcpay-price-skeleton' )
				).toHaveCount( 0 );

				// Fallback prices should be in the store's default currency.
				const priceText = await convertedPrice
					.first()
					.textContent();
				expect( priceText ).toContain( defaultCurrencySymbol );
			} finally {
				await shopperContext?.close();
			}
		} );

		test( 'should use server-side rendering when currency is set via URL', async ( {
			browser,
		} ) => {
			const { shopperPage, shopperContext } =
				await getAnonymousShopper( browser );

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
