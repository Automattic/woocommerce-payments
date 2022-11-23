/**
 * External dependencies
 */
import config from 'config';
const { merchant, shopper, withRestApi } = require( '@woocommerce/e2e-utils' );
import {
	RUN_SUBSCRIPTIONS_TESTS,
	describeif,
	merchantWCP,
	shopperWCP,
	uiLoaded,
} from '../../../utils';
import { fillCardDetails, setupCheckout } from '../../../utils/payments';

const productName = 'Subscription signup fee product';
const productSlug = 'subscription-signup-fee-product';
const customerBilling = config.get(
	'addresses.subscriptions-customer.billing'
);
let orderId;

const testSelectors = {
	checkoutOrderId: '.woocommerce-order-overview__order.order > strong',
	adminOrderTransactionLink: 'p.order_number > a',
	adminTransactionDetails: 'li.woocommerce-timeline-item',
	adminSubscriptionStatus: '.subscription-status',
	adminSubscriptionProductName: '.order-item',
	adminSubscriptionRecurringTotal: '.recurring_total',
};

describeif( RUN_SUBSCRIPTIONS_TESTS )(
	'Subscriptions > Purchase subscription with signup fee',
	() => {
		beforeAll( async () => {
			// Delete the user, if present
			await withRestApi.deleteCustomerByEmail( customerBilling.email );
		} );
		afterAll( async () => {
			await merchant.logout();
		} );

		it( 'should be able to purchase a subscription with signup fee', async () => {
			await shopperWCP.addToCartBySlug( productSlug );

			await setupCheckout( customerBilling );
			const card = config.get( 'cards.basic' );
			await fillCardDetails( page, card );
			await shopper.placeOrder();
			await expect( page ).toMatch( 'Order received' );

			// Get the order ID so we can open it in the merchant view
			const orderIdField = await page.$( testSelectors.checkoutOrderId );
			orderId = await orderIdField.evaluate( ( el ) => el.innerText );

			await shopper.logout();
		} );

		it( 'should have a charge for subscription cost with fee & an active subscription', async () => {
			await merchant.login();

			await merchant.goToOrder( orderId );

			// Pull out and follow the link to avoid working in multiple tabs
			const paymentDetailsLink = await page.$eval(
				testSelectors.adminOrderTransactionLink,
				( anchor ) => anchor.getAttribute( 'href' )
			);
			await Promise.all( [
				page.goto( paymentDetailsLink, {
					waitUntil: 'networkidle0',
				} ),
				uiLoaded(),
			] );

			await expect( page ).toMatchElement(
				testSelectors.adminTransactionDetails,
				{
					text: 'A payment of $11.98 was successfully charged.',
				}
			);

			await merchantWCP.openSubscriptions();

			// Verify we have an active subscription for the product
			await expect( page ).toMatchElement(
				testSelectors.adminSubscriptionStatus,
				{
					text: 'Active',
				}
			);
			await expect( page ).toMatchElement(
				testSelectors.adminSubscriptionProductName,
				{
					text: productName,
				}
			);
			await expect( page ).toMatchElement(
				testSelectors.adminSubscriptionRecurringTotal,
				{
					text: '$9.99 / month',
				}
			);
		} );
	}
);
