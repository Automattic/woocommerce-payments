/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';
/**
 * Internal dependencies
 */
import { getMerchant, getShopper } from '../../utils/helpers';
import {
	activateMulticurrency,
	addCurrency,
	deactivateMulticurrency,
	removeCurrency,
} from '../../utils/merchant';
import { placeOrderWithCurrency } from '../../utils/shopper';
import * as navigation from '../../utils/shopper-navigation';

test.describe( 'Shopper Multi-Currency checkout', () => {
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
		await removeCurrency( merchantPage, 'EUR' );

		if ( ! wasMulticurrencyEnabled ) {
			await deactivateMulticurrency( merchantPage );
		}
	} );

	test.describe( `Checkout with multiple currencies`, async () => {
		Object.keys( currenciesOrders ).forEach( ( currency: string ) => {
			test( `checkout with ${ currency }`, async () => {
				currenciesOrders[ currency ] = await placeOrderWithCurrency(
					shopperPage,
					currency
				);
			} );

			test( `should display ${ currency } in the order received page`, async () => {
				await expect(
					await shopperPage.locator(
						'.woocommerce-order-overview__total'
					)
				).toHaveText( new RegExp( currency ) );
			} );

			test( `should display ${ currency } in the customer order page`, async () => {
				await navigation.goToOrder(
					shopperPage,
					currenciesOrders[ currency ]
				);
				await expect(
					shopperPage.locator(
						'.woocommerce-table--order-details tfoot tr:last-child td'
					)
				).toHaveText( new RegExp( currency ) );
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
							has: shopperPage.getByLabel(
								`View order number ${ currenciesOrders[ currency ] }`
							),
						} )
					).toHaveText( new RegExp( currency ) );
				}
			}
		} );
	} );
} );
