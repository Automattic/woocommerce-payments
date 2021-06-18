/**
 * External dependencies
 */
import config from 'config';

const {
	merchant,
	shopper,
	withRestApi,
	evalAndClick,
} = require( '@woocommerce/e2e-utils' );

import {
	RUN_SUBSCRIPTIONS_TESTS,
	RUN_ACTION_SCHEDULER_TESTS,
	describeif,
	merchantWCP,
} from '../../utils';

import { fillCardDetails, setupCheckout } from '../../utils/payments';

const productName = 'Subscription for systems renewal';
const productSlug = 'subscription-for-systems-renewal';
const actionSchedulerHook = 'woocommerce_scheduled_subscription_payment';
const customerBilling = config.get( 'addresses.customer.billing' );
let orderId;
const subscriptionId = parseInt(orderId) + 1;

describeif( RUN_SUBSCRIPTIONS_TESTS, RUN_ACTION_SCHEDULER_TESTS )(
	'Subscriptions > Renew a subscription via Action Scheduler',
	() => {
		beforeAll( async () => {
			await merchant.login();

			// Create subscription product with signup fee
			await merchantWCP.createSubscriptionProduct( productName, true );

			await merchant.logout();

			// Open the subscription product we created in the store
			await page.goto( config.get( 'url' ) + `product/${ productSlug }`, {
				waitUntil: 'networkidle0',
			} );

			// Add it to the cart and proceed to check out
			await expect( page ).toClick( '.single_add_to_cart_button' );
			await page.waitForNavigation( { waitUntil: 'networkidle0' } );
			await setupCheckout( customerBilling );
			const card = config.get( 'cards.basic' );
			await fillCardDetails( page, card );
			await shopper.placeOrder();
			await expect( page ).toMatch( 'Order received' );

			// Get the order ID so we can open it in the merchant view
			const orderIdField = await page.$(
				'.woocommerce-order-overview__order.order > strong'
			);
			orderId = await orderIdField.evaluate( ( el ) => el.innerText );

			await merchant.login();
		} );

		afterAll( async () => {
			// Delete the user created with the subscription
			await withRestApi.deleteCustomerByEmail( customerBilling.email );
			await merchant.logout();
		} );

		it( 'should be able to renew a subscription via Action Scheduler', async () => {
			// Go to Action Scheduler
			await merchantWCP.openActionScheduler();

			// Filter results by pending
			await page.click( 'ul.subsubsub > .pending > a' );
			await page.waitForNavigation( { waitUntil: 'networkidle0' } );

			// Search by pending subscriptions
			await expect( page ).toFill( 'input#plugin-search-input', actionSchedulerHook );
			await expect( page ).toClick( 'input#search-submit.button' );
			await page.waitForNavigation( { waitUntil: 'networkidle0' } );

			// Run the Action Scheduler task to renew a subscription
			await evalAndClick( 'div.row-actions > span.run > a' );
			await page.waitForNavigation( { waitUntil: 'networkidle0' } );
			await expect( page ).toMatchElement( 'div#message.updated > p > strong', {
				text: actionSchedulerHook
			} );
		} );

		it( 'should verify that the subscription has been renewed', async () => {
			// Go to Subscriptions and verify the subscription renewal
			await merchantWCP.openSubscriptions();
			await expect( page ).toMatchElement( 'tbody#the-list > tr#post-' + subscriptionId + ' > td.orders.column-orders > a', {text: '2'} );
		} );
	}
);
