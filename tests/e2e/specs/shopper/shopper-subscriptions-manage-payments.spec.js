/**
 * External dependencies
 */
import config from 'config';
const customerBilling = config.get( 'addresses.customer.billing' );
const {
	shopper,
	merchant,
	withRestApi,
	setCheckbox,
} = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import {
	RUN_SUBSCRIPTIONS_TESTS,
	describeif,
	shopperWCP,
	merchantWCP,
} from '../../utils';
import { fillCardDetails, setupCheckout } from '../../utils/payments';

const productName = 'Subscription for manage payments';
const productSlug = 'subscription-for-manage-payments';

let subscriptionId;

describeif( RUN_SUBSCRIPTIONS_TESTS )(
	'Shopper > Subscriptions > Manage Payment Methods',
	() => {
		beforeAll( async () => {
			// Create subscription product
			await merchant.login();
			await merchantWCP.createSubscriptionProduct(
				productName,
				'month',
				false
			);
			await merchant.logout();
		} );

		afterAll( async () => {
			// Delete the user created with the subscription
			await withRestApi.deleteCustomerByEmail( customerBilling.email );
		} );

		it( 'should change a default payment method to a new one', async () => {
			// Purchase recently created subscription as a customer
			await page.goto( config.get( 'url' ) + `product/${ productSlug }`, {
				waitUntil: 'networkidle0',
			} );
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

			await shopperWCP.goToSubscriptions();

			// Try to click on the button "Change payment"
			// Otherwise click the subscription ID first and then proceed further
			// Note: This is in case we have multiple subscriptions
			try {
				await page.click( 'a.button.change_payment_method' );
			} catch ( error ) {
				await page.click(
					'.woocommerce-orders-table__cell-subscription-id > a',
					{
						text: subscriptionId,
					}
				);
				await page.waitForSelector( 'a.button.change_payment_method' );
				await page.click( 'a.button.change_payment_method' );
			}

			// Make sure we're on the proper page
			await page.waitForSelector(
				'h1.entry-title',
				'Change payment method'
			);

			// Check to use a new payment method
			await setCheckbox( '#wc-woocommerce_payments-payment-token-new' );

			// Fill a new payment details
			const newCard = config.get( 'cards.basic2' );
			await fillCardDetails( page, newCard );
			await Promise.all( [
				page.click( '#place_order' ),
				page.waitForNavigation( { waitUntil: 'networkidle0' } ),
			] );
			await expect( page ).toMatchElement( 'div.woocommerce-message', {
				text: 'Payment method updated.',
			} );

			// Verify the new payment method has been set
			await expect( page ).toMatchElement(
				'.subscription-payment-method',
				{ text: 'Visa ending in 1111 (expires 11/25)' }
			);
		} );

		it( 'should set a payment method to a different one', async () => {
			// Click to change a payment method
			await expect( page ).toClick( 'a.button.change_payment_method' );
			await page.waitForSelector(
				'h1.entry-title',
				'Change payment method'
			);

			// Select a different payment method and save it
			const checkboxes = await page.$$(
				'.woocommerce-SavedPaymentMethods-tokenInput'
			);
			await checkboxes[ 0 ].click();
			await Promise.all( [
				page.click( '#place_order' ),
				page.waitForNavigation( { waitUntil: 'networkidle0' } ),
			] );
			await expect( page ).toMatchElement( 'div.woocommerce-message', {
				text: 'Payment method updated.',
			} );

			// Verify the new payment method has been set
			await expect( page ).toMatchElement(
				'.subscription-payment-method',
				{ text: 'Visa ending in 4242 (expires 02/24)' }
			);
		} );
	}
);
