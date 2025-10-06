/**
 * External dependencies
 */
import { BrowserContext, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { test, expect, getAuthState } from '../../../fixtures/auth';
import { config } from '../../../config/default';
import * as merchant from '../../../utils/merchant';
import * as shopper from '../../../utils/shopper';
import * as navigation from '../../../utils/shopper-navigation';
import { isUIUnblocked } from '../../../utils/helpers';

test.describe( 'Multi-currency checkout', { tag: '@shopper' }, () => {
	let merchantContext: BrowserContext;
	let merchantPage: Page;
	let shopperContext: BrowserContext;
	let shopperPage: Page;
	let wasMulticurrencyEnabled: boolean;
	let originalEnabledCurrencies: string[];
	const currenciesOrders: Record< string, string | null > = {
		USD: null,
		EUR: null,
	};

	test.beforeAll( async ( { browser } ) => {
		merchantContext = await browser.newContext( {
			storageState: await getAuthState( browser, 'admin' ),
		} );
		merchantPage = await merchantContext.newPage();

		shopperContext = await browser.newContext( {
			storageState: await getAuthState( browser, 'customer' ),
		} );
		shopperPage = await shopperContext.newPage();

		originalEnabledCurrencies = await merchant.getEnabledCurrenciesSnapshot(
			merchantPage
		);
		wasMulticurrencyEnabled = await merchant.activateMulticurrency(
			merchantPage
		);
		await merchant.addCurrency( merchantPage, 'EUR' );
	} );

	test.afterAll( async () => {
		await merchant.restoreCurrencies(
			merchantPage,
			originalEnabledCurrencies
		);
		await shopper.emptyCart( shopperPage );

		if ( ! wasMulticurrencyEnabled ) {
			await merchant.deactivateMulticurrency( merchantPage );
		}

		await merchantContext?.close();
		await shopperContext?.close();
	} );

	test.describe( 'Checkout with multiple currencies', () => {
		for ( const currency of Object.keys( currenciesOrders ) ) {
			test( `checkout with ${ currency }`, async () => {
				await test.step( `pay with ${ currency }`, async () => {
					currenciesOrders[
						currency
					] = await shopper.placeOrderWithCurrency(
						shopperPage,
						currency
					);
				} );

				await test.step(
					`should display ${ currency } in the order received page`,
					async () => {
						await expect(
							shopperPage.locator(
								'.woocommerce-order-overview__total'
							)
						).toHaveText( new RegExp( currency ) );
					}
				);

				await test.step(
					`should display ${ currency } in the customer order page`,
					async () => {
						const orderId = currenciesOrders[ currency ];
						expect( orderId ).toBeTruthy();
						if ( ! orderId ) {
							return;
						}
						await navigation.goToOrder( shopperPage, orderId );
						await expect(
							shopperPage.getByRole( 'cell', {
								name: /\$?\d\d[\.,]\d\d\s€?\s?[A-Z]{3}/,
							} )
						).toHaveText( new RegExp( currency ) );
					}
				);
			} );
		}
	} );

	test.describe( 'My account', () => {
		test( 'should display the correct currency in the my account order history table', async () => {
			await navigation.goToOrders( shopperPage );

			for ( const [ currency, orderId ] of Object.entries(
				currenciesOrders
			) ) {
				if ( ! orderId ) {
					continue;
				}

				await expect(
					shopperPage.locator( 'tr' ).filter( {
						has: shopperPage.getByText( `#${ orderId }` ),
					} )
				).toHaveText( new RegExp( currency ) );
			}
		} );
	} );

	test.describe( 'Available payment methods', () => {
		let originalStoreCurrency = 'USD';

		test.beforeAll( async () => {
			originalStoreCurrency = await merchant.getDefaultCurrency(
				merchantPage
			);
			await merchant.enablePaymentMethods( merchantPage, [
				'Bancontact',
			] );
		} );

		test.afterAll( async () => {
			await merchant.disablePaymentMethods( merchantPage, [
				'Bancontact',
			] );
			await merchant.setDefaultCurrency(
				merchantPage,
				originalStoreCurrency
			);
		} );

		test.beforeEach( async () => {
			await shopper.emptyCart( shopperPage );
		} );

		test( 'should display EUR payment methods when switching to EUR and default is USD', async () => {
			await merchant.setDefaultCurrency( merchantPage, 'USD' );

			await shopper.addToCartFromShopPage(
				shopperPage,
				config.products.simple,
				'USD'
			);
			await navigation.goToCheckout( shopperPage );
			await shopper.fillBillingAddress(
				shopperPage,
				config.addresses[ 'upe-customer' ].billing.be
			);
			await shopper.selectPaymentMethod( shopperPage );
			await expect(
				shopperPage.getByText( 'Bancontact' )
			).not.toBeVisible();

			await navigation.goToCheckout( shopperPage, {
				currency: 'EUR',
			} );
			await shopper.fillBillingAddress(
				shopperPage,
				config.addresses[ 'upe-customer' ].billing.be
			);
			await shopper.selectPaymentMethod( shopperPage );
			await expect( shopperPage.getByText( 'Bancontact' ) ).toBeVisible();

			await isUIUnblocked( shopperPage );
			await shopperPage.getByText( 'Bancontact' ).click();
			await shopperPage.waitForSelector(
				'#payment_method_woocommerce_payments_bancontact:checked',
				{ timeout: 10_000 }
			);

			await shopper.focusPlaceOrderButton( shopperPage );
			await shopper.placeOrder( shopperPage );
			await shopperPage
				.getByRole( 'link', { name: 'Authorize Test Payment' } )
				.click();
			await expect(
				shopperPage.getByText( 'Order received' ).first()
			).toBeVisible();
		} );

		test( 'should display USD payment methods when switching to USD and default is EUR', async () => {
			await merchant.setDefaultCurrency( merchantPage, 'EUR' );

			await shopper.addToCartFromShopPage(
				shopperPage,
				config.products.simple,
				'EUR'
			);
			await navigation.goToCheckout( shopperPage );
			await shopper.fillBillingAddress(
				shopperPage,
				config.addresses[ 'upe-customer' ].billing.be
			);
			await shopper.selectPaymentMethod( shopperPage );
			await expect( shopperPage.getByText( 'Bancontact' ) ).toBeVisible();

			await navigation.goToCheckout( shopperPage, {
				currency: 'USD',
			} );
			await shopper.fillBillingAddress(
				shopperPage,
				config.addresses[ 'upe-customer' ].billing.be
			);
			await shopper.selectPaymentMethod( shopperPage );
			await expect(
				shopperPage.getByText( 'Bancontact' )
			).not.toBeVisible();

			await shopper.fillCardDetails( shopperPage );
			await shopper.focusPlaceOrderButton( shopperPage );
			await shopper.placeOrder( shopperPage );
			await expect(
				shopperPage.getByText( 'Order received' ).first()
			).toBeVisible();
		} );
	} );
} );
