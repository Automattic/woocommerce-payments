/**
 * External dependencies
 */
import config from 'config';
const {
	shopper,
	merchant,
	withRestApi,
	uiUnblocked,
} = require( '@woocommerce/e2e-utils' );
/**
 * Internal dependencies
 */
import {
	fillCardDetails,
	setupCheckout,
	setupProductCheckout,
} from '../../../utils/payments';
import {
	RUN_SUBSCRIPTIONS_TESTS,
	describeif,
	merchantWCP,
	shopperWCP,
} from '../../../utils';

const notice = 'div.wc-block-components-notice-banner';
const oldNotice = 'div.woocommerce-NoticeGroup > ul.woocommerce-error > li';
const waitForBanner = async ( errorText ) => {
	return shopperWCP.waitForErrorBanner( errorText, notice, oldNotice );
};

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
		await expect( page ).toMatch( 'Order received' );

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

	describeif( RUN_SUBSCRIPTIONS_TESTS )(
		'Subscriptions, Stripe Billing, and Multi-currency',
		() => {
			let wasStripeBillingEnabled;

			const productSlug = 'subscription-no-signup-fee-product';
			const customerBilling = config.get(
				'addresses.subscriptions-customer.billing'
			);

			const currencies = [ 'USD', 'EUR' ];

			beforeAll( async () => {
				// Reset customer info.
				withRestApi.deleteCustomerByEmail( customerBilling.email );

				await merchant.login();
				wasStripeBillingEnabled = await merchantWCP.isStripeBillingEnabled();
				if ( ! wasStripeBillingEnabled ) {
					await merchantWCP.activateStripeBilling();
				}
				await merchant.logout();
			} );

			afterAll( async () => {
				await shopper.logout();

				if ( ! wasStripeBillingEnabled ) {
					await merchant.login();
					await merchantWCP.deactivateStripeBilling();
					await merchant.logout();
				}
			} );

			it( 'should place order via Stripe Billing in one currency', async () => {
				// Setup cart and checkout.
				await shopperWCP.goToShopWithCurrency( currencies[ 0 ] );
				await shopperWCP.addToCartBySlug( productSlug );
				// Make sure that the number of items in the cart is incremented first before adding another item.
				await expect( page ).toMatchElement( '.cart-contents .count', {
					text: new RegExp( '1 item' ),
					timeout: 30000,
				} );

				await setupCheckout( customerBilling );

				// Pay for subscription.
				const card = config.get( 'cards.basic' );
				await fillCardDetails( page, card );
				await shopper.placeOrder();

				await expect( page ).toMatch( 'Order received' );

				const url = await page.url();
				const orderId = url.match( /\/order-received\/(\d+)\// )[ 1 ];

				await shopper.goToOrders();
				const orderTotal = await getOrderTotalTextForOrder( orderId );

				expect( orderTotal ).toEqual(
					`$9.99 ${ currencies[ 0 ] } for 1 item`
				);
			} );

			it( 'should not be able to place order via Stripe Billing in another currency', async () => {
				// Setup cart and checkout.
				await shopperWCP.goToShopWithCurrency( currencies[ 1 ] );
				await shopperWCP.addToCartBySlug( productSlug );
				// Make sure that the number of items in the cart is incremented first before adding another item.
				await expect( page ).toMatchElement( '.cart-contents .count', {
					text: new RegExp( '1 item' ),
					timeout: 30000,
				} );

				await setupCheckout( customerBilling );

				// Pay for subscription.
				const card = config.get( 'cards.basic' );
				await fillCardDetails( page, card );

				await expect( page ).toClick( '#place_order' );
				await uiUnblocked();
				await waitForBanner(
					// eslint-disable-next-line max-len
					'There was a problem creating your subscription. All your active subscriptions must use the same currency. You attempted to purchase a subscription in EUR but have another active subscription using USD.'
				);
			} );
		}
	);
} );
