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

const testSelectors = {
	subscriptionIdField: '.woocommerce-orders-table__cell-subscription-id > a',
	subscriptionChangePaymentButton:
		'.subscription_details a.button.change_payment_method',
	wcNotice: '.woocommerce .woocommerce-message',
	pageTitle: 'h1.entry-title',
	newPaymentMethodCheckbox: 'input#wc-woocommerce_payments-payment-token-new',
	subscriptionPaymentMethod: '.subscription-payment-method',
	savedTokensCheckboxes:
		'.payment_method_woocommerce_payments .woocommerce-SavedPaymentMethods-tokenInput',
};

describeif( RUN_SUBSCRIPTIONS_TESTS )(
	'Shopper > Subscriptions > Manage Payment Methods',
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

		it( 'should change a default payment method to a new one', async () => {
			await shopperWCP.goToSubscriptions();
			await expect( page ).toClick( testSelectors.subscriptionIdField, {
				text: subscriptionId,
			} );
			await page.waitForSelector(
				testSelectors.subscriptionChangePaymentButton
			);
			await expect( page ).toClick(
				testSelectors.subscriptionChangePaymentButton
			);
			await page.waitForNavigation( { waitUntil: 'networkidle0' } );

			// Make sure we're on the proper page
			await page.waitForSelector(
				testSelectors.pageTitle,
				'Change payment method'
			);

			await page.waitFor( 1000 );
			await expect( page ).toMatchElement(
				testSelectors.newPaymentMethodCheckbox
			);
			await setCheckbox( testSelectors.newPaymentMethodCheckbox );

			// Fill a new payment details
			const newCard = config.get( 'cards.basic2' );
			await fillCardDetails( page, newCard );

			await shopper.placeOrder();
			await page.waitForSelector( testSelectors.wcNotice, {
				text: 'Payment method updated.',
			} );

			// Verify the new payment method has been set
			await page.waitForSelector(
				testSelectors.subscriptionPaymentMethod,
				{
					text: 'Visa ending in 1111 (expires 11/25)',
				}
			);
		} );

		it( 'should set a payment method to an already saved card', async () => {
			await shopperWCP.goToSubscriptions();
			await expect( page ).toClick( testSelectors.subscriptionIdField, {
				text: subscriptionId,
			} );
			await page.waitForSelector(
				testSelectors.subscriptionChangePaymentButton
			);
			await expect( page ).toClick(
				testSelectors.subscriptionChangePaymentButton
			);
			await page.waitForNavigation( { waitUntil: 'networkidle0' } );

			// Make sure we're on the proper page
			await page.waitForSelector(
				testSelectors.pageTitle,
				'Change payment method'
			);

			await page.waitFor( 1000 );

			// Select a different payment method and save it
			const checkboxes = await page.$$(
				testSelectors.savedTokensCheckboxes
			);
			await checkboxes[ 0 ].click();
			await shopper.placeOrder();
			await page.waitForSelector( testSelectors.wcNotice, {
				text: 'Payment method updated.',
			} );

			// Verify the new payment method has been set
			await page.waitForSelector(
				testSelectors.subscriptionPaymentMethod,
				{
					text: 'Visa ending in 4242 (expires 02/24)',
				}
			);
		} );
	}
);
