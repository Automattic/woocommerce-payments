/**
 * External dependencies
 */
import config from 'config';

const {
	merchant,
	shopper,
	withRestApi,
	setCheckbox,
} = require( '@woocommerce/e2e-utils' );

import {
	RUN_SUBSCRIPTIONS_TESTS,
	describeif,
	merchantWCP,
	shopperWCP,
} from '../../utils';

import { fillCardDetails, setupCheckout } from '../../utils/payments';

const productName = `Subscription product ${ Date.now() }`;
const customerBilling = config.get( 'addresses.customer.billing' );
const cardAtCheckout = config.get( 'cards.basic' );
const newCard = config.get( 'cards.basic2' );
const savedCard = config.get( 'cards.basic3' );

const selectNewCard = async () => {
	// In the second block, select 'Use a new payment method' option.
	await setCheckbox( '#wc-woocommerce_payments-payment-token-new' );

	// Provide a new test card number, future expiration date, and a CVC code.
	await fillCardDetails( page, newCard );
};

const selectSavedCard = async () => {
	// In the second block, select the saved card.
	await shopperWCP.selectSavedPaymentMethod( savedCard.label );
};

const dataTable = [
	[ 'new', newCard, selectNewCard ],
	[ 'saved', savedCard, selectSavedCard ],
];

let productId;
let subscriptionUrl;

describeif( RUN_SUBSCRIPTIONS_TESTS )(
	'Subscriptions > Change payment method',
	() => {
		// Setup the subscription product.
		// Take note of the the product ID.
		beforeAll( async () => {
			await merchant.login();
			productId = await merchantWCP.createSubscriptionProduct(
				productName,
				'month'
			);
			await merchant.logout();

			// As a Shopper purchase the product.
			await shopper.goToProduct( productId );
			await shopper.addToCart();
			await setupCheckout( customerBilling );
			await fillCardDetails( page, cardAtCheckout );
			await shopper.placeOrder();
			subscriptionUrl = await page.$eval( '.subscription-id a', ( a ) =>
				a.getAttribute( 'href' )
			);

			// Add new payment method
			await shopperWCP.goToPaymentMethods();
			await shopperWCP.addNewPaymentMethod( 'basic3', savedCard );

			// As a Shopper, navigate to 'My account -> Subscriptions' and open the newly created subscription entry.
			await page.goto( subscriptionUrl, { waitUntil: 'networkidle0' } );
		}, 200000 );

		afterAll( async () => {
			// Delete the user created with the subscription
			await withRestApi.deleteCustomerByEmail( customerBilling.email );
		} );

		describe.each( dataTable )(
			'Change to a %s card',
			( testName, card, selectNewOrSaved ) => {
				it( `should be able to change to a ${ testName } card`, async () => {
					// Observe 3 buttons for 'Actions'.
					await expect( page ).toMatchElement( '.button.cancel', {
						text: 'Cancel',
					} );
					await expect( page ).toMatchElement(
						'.button.change_payment_method',
						{
							text: 'Change payment',
						}
					);
					await expect( page ).toMatchElement(
						'.button.subscription_renewal_early',
						{
							text: 'Renew now',
						}
					);

					// Click 'Change payment' button
					await expect( page ).toClick(
						'.button.change_payment_method'
					);
					await page.waitForNavigation( {
						waitUntil: 'networkidle0',
					} );

					// Select a new or saved payment method, depending on the scenario currently being tested.
					await selectNewOrSaved();

					// Click 'Change payment method' button.
					await expect( page ).toClick( '#place_order' );
					await page.waitForNavigation( {
						waitUntil: 'networkidle0',
					} );

					// Observe the 'Payment method updated.' success message.
					await expect( page ).toMatchElement(
						'.woocommerce-message',
						{
							text: 'Payment method updated.',
						}
					);

					// Ensure the 'Payment' line in the very first block is reflecting the card data you have provided.
					const { label, expires } = card;
					const { month, year } = expires;
					await expect( page ).toMatchElement(
						'.shop_table.subscription_details',
						{
							text: `${ label } (expires ${ month }/${ year })`,
						}
					);
				} );
			}
		);
	}
);
