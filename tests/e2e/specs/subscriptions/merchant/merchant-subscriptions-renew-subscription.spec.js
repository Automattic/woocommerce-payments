/**
 * External dependencies
 */
import config from 'config';
const {
	merchant,
	shopper,
	withRestApi,
	evalAndClick,
	uiUnblocked,
} = require( '@woocommerce/e2e-utils' );
import {
	RUN_SUBSCRIPTIONS_TESTS,
	describeif,
	merchantWCP,
} from '../../../utils';
import { fillCardDetails, setupCheckout } from '../../../utils/payments';

const productSlug = 'subscription-signup-fee-product';
const customerBilling = config.get(
	'addresses.subscriptions-customer.billing'
);
let subscriptionId;

/*
 * This test has dependencies on components like Action Scheduler and there is
 * no guarantee in the test environment that it won't be overloaded with other
 * tasks, e.g. image regeneration. Hence, it is better to skip test until we
 * can find a way to create a "pure" environment without any background tasks.
 */
describeif( RUN_SUBSCRIPTIONS_TESTS )(
	'Subscriptions > Renew a subscription as a merchant',
	() => {
		beforeAll( async () => {
			// Delete the user created with the subscription
			await withRestApi.deleteCustomerByEmail( customerBilling.email );

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

			// Get the subscription ID
			const subscriptionIdField = await page.$(
				'.woocommerce-orders-table__cell-subscription-id > a'
			);
			subscriptionId = await subscriptionIdField.evaluate(
				( el ) => el.innerText
			);

			await shopper.logout();
		} );
		afterAll( async () => {
			await merchant.logout();
		} );

		it( 'should be able to renew a subscription as a merchant', async () => {
			await merchant.login();

			// Open the specific subscription detail page
			await merchantWCP.openSubscriptions();
			await expect( page ).toClick( 'div.tips > a', {
				text: subscriptionId,
			} );
			await page.waitForNavigation( { waitUntil: 'networkidle0' } );
			await expect( page ).toMatchElement( 'h1', {
				text: 'Edit Subscription',
			} );
			await expect( page ).toSelect(
				'select[name="wc_order_action"]',
				'Process renewal'
			);
			await Promise.all( [
				page.removeAllListeners( 'dialog' ),
				evalAndClick( 'button.save_order' ),
				page.on( 'dialog', async ( dialog ) => {
					await dialog.accept();
				} ),
				uiUnblocked(),
				page.waitForNavigation( { waitUntil: 'networkidle0' } ),
			] );

			// Check if a new order is present in related orders
			await expect(
				page
			).toMatchElement(
				'div.woocommerce_subscriptions_related_orders > table > tbody > tr > td',
				{ text: 'Renewal Order' }
			);
		} );
	}
);
