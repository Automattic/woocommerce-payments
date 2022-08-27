/**
 * External dependencies
 */
import config from 'config';

const { merchant, shopper, withRestApi } = require( '@woocommerce/e2e-utils' );

import {
	RUN_SUBSCRIPTIONS_TESTS,
	describeif,
	merchantWCP,
} from '../../../utils';

import { fillCardDetails, setupCheckout } from '../../../utils/payments';

const productName = 'Subscription free trial product';
const productSlug = 'subscription-free-trial-product';

const customerBilling = config.get( 'addresses.customer.billing' );

let orderId;
let subscriptionNextPaymentDate;

describeif( RUN_SUBSCRIPTIONS_TESTS )(
	'Subscriptions > Purchase a simple subscription',
	() => {
		afterAll( async () => {
			// Delete the user created with the subscription
			await withRestApi.deleteCustomerByEmail( customerBilling.email );
		} );

		it( 'should be able to purchase a simple subscription', async () => {
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

			// Get the order ID so we can open it in the merchant view
			const orderIdField = await page.$(
				'.woocommerce-order-overview__order.order > strong'
			);
			orderId = await orderIdField.evaluate( ( el ) => el.innerText );

			//
			const subscriptionNextPaymentDateField = await page.$(
				'.woocommerce-orders-table--subscriptions td.subscription-next-payment'
			);
			subscriptionNextPaymentDate = await subscriptionNextPaymentDateField.evaluate(
				( el ) => el.innerText
			);

			await shopper.logout();
		} );

		it( 'should have an active subscription with trial end date', async () => {
			await merchant.login();

			await merchant.goToOrder( orderId );

			// Confirm setup intent is present on order
			await expect( page ).toMatchElement( 'p.order_number > a', {
				text: 'seti',
			} );

			// Check & confirm subscription is active
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
			await expect( page ).toMatchElement(
				'td.column-trial_end_date > time.trial_end_date',
				{
					text: subscriptionNextPaymentDate,
				}
			);
			await expect( page ).toMatchElement(
				'td.column-next_payment_date time.next_payment_date',
				{
					text: subscriptionNextPaymentDate,
				}
			);

			await merchant.logout();
		} );
	}
);
