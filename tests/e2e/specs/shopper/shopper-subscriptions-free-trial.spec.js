/**
 * External dependencies
 */
import config from 'config';

const { merchant, shopper, withRestApi } = require( '@woocommerce/e2e-utils' );

import { RUN_SUBSCRIPTIONS_TESTS, describeif, merchantWCP } from '../../utils';

import {
	fillCardDetails,
	setupCheckout,
	confirmCardAuthentication,
} from '../../utils/payments';

const nowLocal = new Date();
const nowUTC = new Date(
	nowLocal.getUTCFullYear(),
	nowLocal.getUTCMonth(),
	nowLocal.getUTCDate()
);
const formatter = new Intl.DateTimeFormat( 'en-US', {
	dateStyle: 'long',
} );
const renewalDate = nowUTC.setDate( nowUTC.getDate() + 14 );
const renewalDateFormatted = formatter.format( renewalDate );
const productName = `Simple subscription with free trial ${ nowLocal.getTime() }`;
const productSlug = `simple-subscription-with-free-trial-${ nowLocal.getTime() }`;

const customerBilling = config.get( 'addresses.customer.billing' );

let orderId;

describeif( RUN_SUBSCRIPTIONS_TESTS )(
	'Subscriptions > Purchase subscription with free trial',
	() => {
		beforeAll( async () => {
			await merchant.login();

			// Create subscription product without signup fee
			await merchantWCP.createSubscriptionProduct(
				productName,
				'month',
				false,
				true
			);

			await merchant.logout();
		} );

		afterAll( async () => {
			await merchant.logout();

			// Delete the user created with the subscription
			await withRestApi.deleteCustomerByEmail( customerBilling.email );
		} );

		it( 'should be able to purchase a subscription with free trial', async () => {
			// Open the subscription product we created in the store,
			// and verify that the 14-day free trial is shown in the product description
			await page.goto( config.get( 'url' ) + `product/${ productSlug }`, {
				waitUntil: 'networkidle0',
			} );
			await expect( page ).toMatchElement( '.subscription-details', {
				text: '/ month with a 14-day free trial',
			} );

			// Add it to the cart and verify that the cart page shows the free trial details
			await expect( page ).toClick( '.single_add_to_cart_button' );
			await page.waitForNavigation( { waitUntil: 'networkidle0' } );
			await shopper.goToCart();
			await expect( page ).toMatchElement( '.subscription-details', {
				text: '/ month with a 14-day free trial',
			} );

			// Also verify that the first renewal is 14 days from now
			await expect( page ).toMatchElement( '.first-payment-date', {
				text: `First renewal: ${ renewalDateFormatted }`,
			} );

			// Verify that the order total is $0.00
			await expect( page ).toMatchElement( '.order-total', {
				text: '$0.00',
			} );

			// Proceed to the checkout page and verify that the 14-day free trial is shown in the product line item,
			// and that the first renewal date is 14 days from now.
			await setupCheckout( customerBilling );
			await expect( page ).toMatchElement( '.subscription-details', {
				text: '/ month with a 14-day free trial',
			} );
			await expect( page ).toMatchElement( '.first-payment-date', {
				text: `First renewal: ${ renewalDateFormatted }`,
			} );

			// Pay using a 3DS card
			const card = config.get( 'cards.3dsOTP' );
			await fillCardDetails( page, card );
			await expect( page ).toClick( '#place_order' );
			await confirmCardAuthentication( page, '3DS', true );
			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );
			await expect( page ).toMatch( 'Order received' );

			// Get the order ID so we can open it in the merchant view
			const orderIdField = await page.$(
				'.woocommerce-order-overview__order.order > strong'
			);
			orderId = await orderIdField.evaluate( ( el ) => el.innerText );
		} );

		it( 'should create an order with "Setup Intent"', async () => {
			await merchant.login();

			await merchant.goToOrder( orderId );

			await expect( page ).toMatchElement(
				'.woocommerce-order-data__meta',
				{
					text: /\(seti_.*\)/,
				}
			);
		} );

		it( 'should have an active subscription with the correct trial end date', async () => {
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
			await expect( page ).toMatchElement( '.trial_end_date', {
				text: renewalDateFormatted,
			} );
		} );
	}
);
