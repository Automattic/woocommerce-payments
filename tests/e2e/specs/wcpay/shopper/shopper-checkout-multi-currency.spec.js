/**
 * External dependencies
 */
import config from 'config';

const { shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */

import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';
import { shopperWCP } from '../../../utils';

const placeOrderWithCurrency = async ( currency ) => {
	await setupProductCheckout(
		config.get( 'addresses.customer.billing' ),
		[ [ config.get( 'products.simple.name' ), 1 ] ],
		currency
	);
	const card = config.get( 'cards.basic' );
	await fillCardDetails( page, card );
	await shopper.placeOrder();
	await expect( page ).toMatch( 'Order received' );

	const url = await page.url();
	// Extracting the order ID from the URL
	return url.match( /\/order-received\/(\d+)\// )[ 1 ];
};

describe( 'Shopper Multi-Currency checkout', () => {
	const currenciesOrders = {
		USD: null,
		EUR: null,
	};
	beforeAll( async () => {
		await shopper.login();
	} );

	afterAll( async () => {
		await shopperWCP.emptyCart();
	} );

	describe.each( Object.keys( currenciesOrders ) )(
		'Checkout process with %s currency',
		( testCurrency ) => {
			it( 'should allow checkout', async () => {
				currenciesOrders[ testCurrency ] = await placeOrderWithCurrency(
					testCurrency
				);
			} );

			it( 'should display the correct currency on the order received page', async () => {
				// should show correct currency in order received page
				expect(
					await page.$eval(
						'.woocommerce-order-overview__total',
						( el ) => el.textContent
					)
				).toMatch( new RegExp( testCurrency ) );
			} );
		}
	);

	describe.each( Object.keys( currenciesOrders ) )(
		'My account order details for %s order',
		( testCurrency ) => {
			beforeEach( async () => {
				await shopperWCP.goToOrder( currenciesOrders[ testCurrency ] );
			} );

			it( 'should show the correct currency in the order page for the order', async () => {
				expect(
					await page.$eval(
						'.woocommerce-table--order-details tfoot tr:last-child td',
						( el ) => el.textContent
					)
				).toMatch( new RegExp( testCurrency ) );
			} );
		}
	);

	describe( 'My account order history', () => {
		it( 'should show the correct currency in the order history table', async () => {
			await shopperWCP.goToOrders();

			for ( const currency in currenciesOrders ) {
				const orderTotalText = await page.$$eval(
					'.woocommerce-orders-table__row',
					( rows, orderId ) => {
						const orderSelector = `.woocommerce-orders-table__cell-order-number a[href*="view-order/${ orderId }/"]`;
						for ( const row of rows ) {
							if ( row.querySelector( orderSelector ) ) {
								const totalCell = row.querySelector(
									'.woocommerce-orders-table__cell-order-total'
								);
								return totalCell
									? totalCell.textContent.trim()
									: null;
							}
						}
						return null;
					},
					currenciesOrders[ currency ]
				);

				expect( orderTotalText ).toMatch( new RegExp( currency ) );
			}
		} );
	} );
} );
