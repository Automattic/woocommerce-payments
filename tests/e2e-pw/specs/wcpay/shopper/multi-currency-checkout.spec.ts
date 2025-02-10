/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';
/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import { getMerchant, getShopper } from '../../../utils/helpers';
import {
	activateMulticurrency,
	addCurrency,
	deactivateMulticurrency,
	disablePaymentMethods,
	enablePaymentMethods,
	restoreCurrencies,
	setDefaultCurrency,
} from '../../../utils/merchant';
import { goToWooCommerceSettings } from '../../../utils/merchant-navigation';
import * as shopper from '../../../utils/shopper';
import * as navigation from '../../../utils/shopper-navigation';

test.describe( 'Multi-currency checkout', () => {
	let merchantPage: Page;
	let shopperPage: Page;
	let wasMulticurrencyEnabled: boolean;
	const currenciesOrders = {
		USD: null,
		EUR: null,
	};

	test.beforeAll( async ( { browser } ) => {
		shopperPage = ( await getShopper( browser ) ).shopperPage;
		merchantPage = ( await getMerchant( browser ) ).merchantPage;
		wasMulticurrencyEnabled = await activateMulticurrency( merchantPage );

		await addCurrency( merchantPage, 'EUR' );
	} );

	test.afterAll( async () => {
		await restoreCurrencies( merchantPage );
		await shopper.emptyCart( shopperPage );

		if ( ! wasMulticurrencyEnabled ) {
			await deactivateMulticurrency( merchantPage );
		}
	} );

	test.describe( `Checkout with multiple currencies`, async () => {
		Object.keys( currenciesOrders ).forEach( ( currency: string ) => {
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
						await navigation.goToOrder(
							shopperPage,
							currenciesOrders[ currency ]
						);
						await expect(
							shopperPage.locator(
								'.woocommerce-table--order-details tfoot tr:last-child td'
							)
						).toHaveText( new RegExp( currency ) );
					}
				);
			} );
		} );
	} );

	test.describe( 'My account', () => {
		test( 'should display the correct currency in the my account order history table', async () => {
			await navigation.goToOrders( shopperPage );

			for ( const currency in currenciesOrders ) {
				if ( currenciesOrders[ currency ] ) {
					await expect(
						shopperPage.locator( 'tr' ).filter( {
							has: shopperPage.getByText(
								`#${ currenciesOrders[ currency ] }`
							),
						} )
					).toHaveText( new RegExp( currency ) );
				}
			}
		} );
	} );

	test.describe( 'Available payment methods', () => {
		let originalCurrency = 'USD';

		test.beforeAll( async () => {
			await goToWooCommerceSettings( merchantPage, 'general' );
			originalCurrency = await merchantPage
				.locator( '#woocommerce_currency' )
				.inputValue();

			await enablePaymentMethods( merchantPage, [ 'bancontact' ] );
		} );

		test.afterAll( async () => {
			await disablePaymentMethods( merchantPage, [ 'bancontact' ] );
			await setDefaultCurrency( merchantPage, originalCurrency );
		} );

		test.beforeEach( async () => {
			await shopper.emptyCart( shopperPage );
		} );

		test( 'should display EUR payment methods when switching to EUR and default is USD', async () => {
			await setDefaultCurrency( merchantPage, 'USD' );

			// Shopper switch to USD.
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
			await expect(
				shopperPage.getByText( 'Bancontact' )
			).not.toBeVisible();

			// Shopper switch to EUR.
			await navigation.goToCheckout( shopperPage, {
				currency: 'EUR',
			} );
			await shopper.fillBillingAddress(
				shopperPage,
				config.addresses[ 'upe-customer' ].billing.be
			);
			await expect( shopperPage.getByText( 'Bancontact' ) ).toBeVisible();

			// Shopper checkout with Bancontact.
			await shopperPage.getByText( 'Bancontact' ).click();
			await shopper.focusPlaceOrderButton( shopperPage );
			await shopper.placeOrder( shopperPage );
			await shopperPage
				.getByRole( 'link', {
					name: 'Authorize Test Payment',
				} )
				.click();
			await expect(
				shopperPage.getByText( 'Order received' ).first()
			).toBeVisible();
		} );

		test( 'should display USD payment methods when switching to USD and default is EUR', async () => {
			await setDefaultCurrency( merchantPage, 'EUR' );

			// Shopper switch to EUR.
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
			await expect( shopperPage.getByText( 'Bancontact' ) ).toBeVisible();

			// Shopper switch to USD.
			await navigation.goToCheckout( shopperPage, {
				currency: 'USD',
			} );
			await shopper.fillBillingAddress(
				shopperPage,
				config.addresses[ 'upe-customer' ].billing.be
			);
			await expect(
				shopperPage.getByText( 'Bancontact' )
			).not.toBeVisible();

			// Shopper checkout with CC.
			await shopper.fillCardDetails( shopperPage );
			await shopper.focusPlaceOrderButton( shopperPage );
			await shopper.placeOrder( shopperPage );
			await expect(
				shopperPage.getByText( 'Order received' ).first()
			).toBeVisible();
		} );
	} );
} );
