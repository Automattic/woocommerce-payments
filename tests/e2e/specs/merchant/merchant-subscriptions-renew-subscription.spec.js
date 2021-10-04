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

import { RUN_SUBSCRIPTIONS_TESTS, describeif, merchantWCP } from '../../utils';

import { fillCardDetails, setupCheckout } from '../../utils/payments';

const productName = 'Subscription for merchant renewal';
const productSlug = 'subscription-for-merchant-renewal';

const customerBilling = config.get( 'addresses.customer.billing' );

let subscriptionId;

describeif( RUN_SUBSCRIPTIONS_TESTS )(
	'Subscriptions > Renew a subscription as a merchant',
	() => {
		beforeAll( async () => {
			await merchant.login();

			// Create subscription product with signup fee
			await merchantWCP.createSubscriptionProduct(
				productName,
				'month',
				true
			);

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

			// Get the subscription ID
			const subscriptionIdField = await page.$(
				'.woocommerce-orders-table__cell-subscription-id > a'
			);
			subscriptionId = await subscriptionIdField.evaluate(
				( el ) => el.innerText
			);

			await merchant.login();
		} );

		afterAll( async () => {
			// Delete the user created with the subscription
			await withRestApi.deleteCustomerByEmail( customerBilling.email );
			await merchant.logout();
		} );

		it( 'should be able to renew a subscription as a merchant', async () => {
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
