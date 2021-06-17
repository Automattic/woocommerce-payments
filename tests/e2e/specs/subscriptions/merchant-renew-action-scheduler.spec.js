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

const productName = 'Subscription for systems renewal';
const productSlug = 'subscription-for-systems-renewal';

const customerBilling = config.get( 'addresses.customer.billing' );

let subscriptionId;

describeif( RUN_SUBSCRIPTIONS_TESTS )(
	'Subscriptions > Renew a subscription as a merchant',
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
			 
		} );
	}
);
