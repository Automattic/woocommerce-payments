/**
 * External dependencies
 */
import config from 'config';
const { shopper, merchant, withRestApi } = require( '@woocommerce/e2e-utils' );
/**
 * Internal dependencies
 */
import { fillCardDetails, setupCheckout } from '../../../utils/payments';
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

const ORDER_HISTORY_ORDER_ROW_SELECTOR = '.woocommerce-orders-table__row';
const ORDER_HISTORY_ORDER_ROW_NUMBER_COL_SELECTOR =
	'.woocommerce-orders-table__cell-order-number';
const ORDER_HISTORY_ORDER_ROW_TOTAL_COL_SELECTOR =
	'.woocommerce-orders-table__cell-order-total';

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

			await setupCheckout( customerBilling );

			// Pay for subscription.
			const card = config.get( 'cards.basic' );
			await fillCardDetails( page, card );
			await shopper.placeOrder();

			await waitForBanner(
				// eslint-disable-next-line max-len
				'There was a problem creating your subscription. All your active subscriptions must use the same currency. You attempted to purchase a subscription in EUR but have another active subscription using USD.'
			);
		} );
	}
);
