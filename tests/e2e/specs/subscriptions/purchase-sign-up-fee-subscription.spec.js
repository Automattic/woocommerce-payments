/**
 * External dependencies
 */
import config from 'config';

const { merchant, shopper } = require( '@woocommerce/e2e-utils' );

import { RUN_SUBSCRIPTIONS_TESTS, describeif, merchantWCP } from '../../utils';

import { fillCardDetails, setupCheckout } from '../../utils/payments';

const productName = 'Subscription signup fee product';
const productSlug = 'subscription-signup-fee-product';

const customerBilling = config.get( 'addresses.customer.billing' );

describeif( RUN_SUBSCRIPTIONS_TESTS )(
	'Subscriptions > Purchase subscription with signup fee',
	() => {
		beforeAll( async () => {
			await merchant.login();

			// Create subscription product with signup fee
			await merchantWCP.createSubscriptionProduct( productName, true );

			await merchant.logout();
		} );

		afterAll( async () => {
			await merchant.logout();
		} );

		it( 'should be able to purchase a subscription with signup fee', async () => {
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
		} );

		it( 'should have an active subscription', async () => {
			await merchant.login();

			await merchantWCP.openSubscriptions();

			// Verify we have an active subscription for the product
			await expect( page ).toMatchElement( '.subscription-status', {
				text: 'Active',
			} );
			await expect( page ).toMatchElement( '.order-item', {
				text: productName,
			} );
			await expect( page ).toMatchElement( '.recurring_total', {
				text: '$9.99 / month',
			} );
		} );
	}
);
