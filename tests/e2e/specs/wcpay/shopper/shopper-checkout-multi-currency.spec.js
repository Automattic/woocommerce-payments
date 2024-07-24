/**
 * External dependencies
 */
import config from 'config';
const { shopper, merchant } = require( '@woocommerce/e2e-utils' );
/**
 * Internal dependencies
 */
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';
import { merchantWCP, shopperWCP } from '../../../utils';

const ORDER_RECEIVED_ORDER_TOTAL_SELECTOR =
	'.woocommerce-order-overview__total';
const ORDER_HISTORY_ORDER_ROW_SELECTOR = '.woocommerce-orders-table__row';
const ORDER_HISTORY_ORDER_ROW_NUMBER_COL_SELECTOR =
	'.woocommerce-orders-table__cell-order-number';
const ORDER_HISTORY_ORDER_ROW_TOTAL_COL_SELECTOR =
	'.woocommerce-orders-table__cell-order-total';
const ORDER_DETAILS_ORDER_TOTAL_SELECTOR =
	'.woocommerce-table--order-details tfoot tr:last-child td';

const placeOrderWithCurrency = async ( currency ) => {
	try {
		await shopperWCP.goToShopWithCurrency( currency );
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' ),
			[ [ config.get( 'products.simple.name' ), 1 ] ],
			currency
		);
		const card = config.get( 'cards.basic' );
		await fillCardDetails( page, card );
		await shopper.placeOrder();
		await expect( page ).toMatchTextContent( 'Order received' );

		const url = await page.url();
		return url.match( /\/order-received\/(\d+)\// )[ 1 ];
	} catch ( error ) {
		// eslint-disable-next-line no-console
		console.error(
			`Error placing order with currency ${ currency }: `,
			error
		);
		throw error;
	}
};

const getOrderTotalTextForOrder = async ( orderId ) => {
	return await page.$$eval(
		ORDER_HISTORY_ORDER_ROW_SELECTOR,
		( rows, currentOrderId, orderNumberColSelector, totalColSelector ) => {
			const orderSelector = `${ orderNumberColSelector } a[href*="view-order/${ currentOrderId }/"]`;
			return rows
				.filter( ( row ) => row.querySelector( orderSelector ) )
				.map( ( row ) =>
					row.querySelector( totalColSelector )?.textContent.trim()
				)
				.find( ( text ) => text !== null );
		},
		orderId,
		ORDER_HISTORY_ORDER_ROW_NUMBER_COL_SELECTOR,
		ORDER_HISTORY_ORDER_ROW_TOTAL_COL_SELECTOR
	);
};

describe( 'Shopper Multi-Currency checkout', () => {
	let wasMulticurrencyEnabled;
	const currenciesOrders = {
		USD: null,
		EUR: null,
	};
	beforeAll( async () => {
		// Enable multi-currency
		await merchant.login();

		wasMulticurrencyEnabled = await merchantWCP.activateMulticurrency();
		for ( const currency in currenciesOrders ) {
			await merchantWCP.addCurrency( currency );
		}

		await merchant.logout();

		await shopper.login();
	} );

	afterAll( async () => {
		await shopperWCP.emptyCart();
		await shopper.logout();

		// Disable multi-currency if it was not initially enabled.
		if ( ! wasMulticurrencyEnabled ) {
			await merchant.login();
			await merchantWCP.deactivateMulticurrency();
			await merchant.logout();
		}
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
				expect(
					await page.$eval(
						ORDER_RECEIVED_ORDER_TOTAL_SELECTOR,
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
						ORDER_DETAILS_ORDER_TOTAL_SELECTOR,
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
				const orderTotalText = await getOrderTotalTextForOrder(
					currenciesOrders[ currency ]
				);
				expect( orderTotalText ).toMatch( new RegExp( currency ) );
			}
		} );
	} );
} );
