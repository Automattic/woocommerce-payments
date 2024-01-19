/**
 * External dependencies
 */
import config from 'config';
const { shopper, withRestApi } = require( '@woocommerce/e2e-utils' );
import {
	RUN_SUBSCRIPTIONS_TESTS,
	describeif,
	shopperWCP,
} from '../../../utils';
import { fillCardDetails, setupCheckout } from '../../../utils/payments';

const productSlug = 'subscription-signup-fee-product';
const customerBilling = config.get(
	'addresses.subscriptions-customer.billing'
);
let subscriptionId;

const testSelectors = {
	subscriptionIdField: '.woocommerce-orders-table__cell-subscription-id > a',
	subscriptionRenewButton: 'a.button.subscription_renewal_early',
	wcNotice: 'div.wc-block-components-notice-banner',
	wcOldNotice:
		'.woocommerce .woocommerce-notices-wrapper .woocommerce-message',
};

describeif( RUN_SUBSCRIPTIONS_TESTS )(
	'Subscriptions > Renew a subscription in my account',
	() => {
		beforeAll( async () => {
			// Delete the user, if present
			await withRestApi.deleteCustomerByEmail( customerBilling.email );
		} );
		afterAll( async () => {
			await shopper.logout();
		} );

		it( 'should be able to purchase a subscription', async () => {
			// Open the subscription product & add to cart
			await shopperWCP.addToCartBySlug( productSlug );

			// Checkout
			await setupCheckout( customerBilling );
			const card = config.get( 'cards.basic' );
			await fillCardDetails( page, card );
			await shopper.placeOrder();
			await expect( page ).toMatch( 'Order received' );

			// Get the subscription ID
			const subscriptionIdField = await page.$(
				testSelectors.subscriptionIdField
			);
			subscriptionId = await subscriptionIdField.evaluate(
				( el ) => el.innerText
			);
		} );

		it( 'should be able to renew a subscription in my account', async () => {
			// Go to my account and click to renew a subscription
			await shopperWCP.goToSubscriptions();
			await expect( page ).toClick( testSelectors.subscriptionIdField, {
				text: subscriptionId,
			} );
			await page.waitForNavigation( { waitUntil: 'networkidle0' } );
			await expect( page ).toClick(
				testSelectors.subscriptionRenewButton
			);

			// Place an order to renew a subscription
			const newWayPromise = ( async () => {
				return page.waitForSelector( testSelectors.wcNotice, {
					text: 'Complete checkout to renew now.',
				} );
			} )();

			const oldWayPromise = ( async () => {
				return page.waitForSelector( testSelectors.wcOldNotice, {
					text: 'Complete checkout to renew now.',
				} );
			} )();

			await Promise.race( [ newWayPromise, oldWayPromise ] );

			await page.waitForNavigation( { waitUntil: 'networkidle0' } );

			await shopper.placeOrder();
			await expect( page ).toMatch( 'Order received' );
		} );
	}
);
