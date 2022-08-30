/**
 * External dependencies
 */
import config from 'config';
const {
	shopper,
	withRestApi,
	setCheckbox,
} = require( '@woocommerce/e2e-utils' );
import {
	RUN_SUBSCRIPTIONS_TESTS,
	describeif,
	shopperWCP,
} from '../../../utils';
import { fillCardDetails, setupCheckout } from '../../../utils/payments';

const customerBilling = config.get(
	'addresses.subscriptions-customer.billing'
);
const productSlug = 'subscription-no-signup-fee-product';
let subscriptionId;

describeif( RUN_SUBSCRIPTIONS_TESTS )(
	'Shopper > Subscriptions > Manage Payment Methods',
	() => {
		beforeAll( async () => {
			// Delete the user, if present
			await withRestApi.deleteCustomerByEmail( customerBilling.email );
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
			await page.click(
				'.woocommerce-orders-table__cell-subscription-id > a',
				{
					text: subscriptionId,
				}
			);
			await expect( page ).toMatchElement(
				'.subscription_details a.button.change_payment_method',
				{
					text: 'Change payment',
				}
			);

			await page.click(
				'.subscription_details a.button.change_payment_method'
			);

			// Make sure we're on the proper page
			await page.waitForSelector(
				'h1.entry-title',
				'Change payment method'
			);

			await page.waitFor( 1000 );
			await expect( page ).toMatchElement(
				'input#wc-woocommerce_payments-payment-token-new'
			);
			await setCheckbox( '#wc-woocommerce_payments-payment-token-new' );

			// Fill a new payment details
			const newCard = config.get( 'cards.basic2' );
			await fillCardDetails( page, newCard );
			await page.click( '#place_order' );
			await page.waitForNavigation( { waitUntil: 'networkidle0' } );
			await expect( page ).toMatchElement( 'div.woocommerce-message', {
				text: 'Payment method updated.',
			} );

			// Verify the new payment method has been set
			await expect( page ).toMatchElement(
				'.subscription-payment-method',
				{ text: 'Visa ending in 1111 (expires 11/25)' }
			);
		} );

		it( 'should set a payment method to an already saved card', async () => {
			await shopperWCP.goToSubscriptions();
			await page.click(
				'.woocommerce-orders-table__cell-subscription-id > a',
				{
					text: subscriptionId,
				}
			);
			await expect( page ).toMatchElement(
				'.subscription_details a.button.change_payment_method',
				{
					text: 'Change payment',
				}
			);

			await page.click(
				'.subscription_details a.button.change_payment_method'
			);

			// Make sure we're on the proper page
			await page.waitForSelector(
				'h1.entry-title',
				'Change payment method'
			);

			await page.waitFor( 1000 );

			// Select a different payment method and save it
			const checkboxes = await page.$$(
				'.payment_method_woocommerce_payments .woocommerce-SavedPaymentMethods-tokenInput'
			);
			await checkboxes[ 0 ].click();
			await page.click( '#place_order' );
			await expect( page ).toMatchElement( 'div.woocommerce-message', {
				text: 'Payment method updated.',
			} );

			// Verify the new payment method has been set
			await expect( page ).toMatchElement(
				'.subscription-payment-method',
				{ text: 'Visa ending in 4242 (expires 02/24)' }
			);

			await shopper.logout();
		} );
	}
);
