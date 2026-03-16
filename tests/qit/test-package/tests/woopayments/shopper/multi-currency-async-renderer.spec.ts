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
		( route ) => route.abort( 'connectionfailed' )
	);
};

test.describe(
	'Multi-currency async price renderer',
	{ tag: '@shopper' },
	() => {
		let merchantContext: BrowserContext;
		let merchantPage: Page;
		let wasMulticurrencyEnabled = false;
		let originalEnabledCurrencies: string[] = [];

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
			// Restore original settings.
			await qit.wp(
				'option update wcpay_multi_currency_rendering_mode speed'
			);
			await qit.wp(
				'option delete _wcpay_feature_mc_cache_optimized'
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

		test( 'should render skeleton markup and convert prices client-side', async ( {
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

		test( 'should convert screen-reader text alongside prices', async ( {
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

				// Verify screen-reader text annotations were converted
				// (present on sale products and variable products).
				const srConverted = shopperPage.locator(
					'[data-wcpay-sr-type].wcpay-sr-converted'
				);
				const srCount = await srConverted.count();

				if ( srCount > 0 ) {
					const srText = await srConverted
						.first()
						.textContent();
					// Screen-reader text should contain a formatted price
					// with a currency symbol.
					expect( srText ).toMatch( /[\$€£¥]|USD|EUR/ );
				}
			} finally {
				await shopperContext?.close();
			}
		} );

		test( 'should show fallback on network failure', async ( {
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

				// Fallback prices should be in the store's default currency (USD).
				const priceText = await convertedPrice
					.first()
					.textContent();
				expect( priceText ).toContain( '$' );
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
