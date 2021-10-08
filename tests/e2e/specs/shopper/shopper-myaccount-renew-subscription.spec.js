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
} from '../../utils';

import { fillCardDetails, setupCheckout } from '../../utils/payments';

const productName = 'Subscription for renewal testing';
const productSlug = 'subscription-for-renewal-testing';

const customerBilling = config.get( 'addresses.customer.billing' );

let subscriptionId;

describeif( RUN_SUBSCRIPTIONS_TESTS )(
	'Subscriptions > Renew a subscription in my account',
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
		} );

		afterAll( async () => {
			// Delete the user created with the subscription
			await withRestApi.deleteCustomerByEmail( customerBilling.email );
		} );

		it( 'should be able to renew a subscription in my account', async () => {
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

			// Go to my account and click to renew a subscription
			await shopperWCP.goToSubscriptions();
			await expect( page ).toClick(
				'.woocommerce-orders-table__cell-subscription-id > a',
				{
					text: subscriptionId,
				}
			);
			await page.waitForNavigation( { waitUntil: 'networkidle0' } );
			await expect( page ).toClick(
				'a.button.subscription_renewal_early'
			);

			// Place an order to renew a subscription
			await page.waitForSelector(
				'.woocommerce > div.woocommerce-notices-wrapper > div.woocommerce-message'
			);
			await expect( page ).toMatchElement(
				'.woocommerce > div.woocommerce-notices-wrapper > div.woocommerce-message',
				{
					text: 'Complete checkout to renew now.',
				}
			);
			await page.waitForNavigation( { waitUntil: 'networkidle0' } );
			await shopper.placeOrder();
			await expect( page ).toMatch( 'Order received' );
		} );
	}
);
