/**
 * External dependencies
 */
import config from 'config';
const { merchant, shopper, withRestApi } = require( '@woocommerce/e2e-utils' );
import {
	RUN_SUBSCRIPTIONS_TESTS,
	describeif,
	merchantWCP,
	uiLoaded,
} from '../../../utils';
import { fillCardDetails, setupCheckout } from '../../../utils/payments';

const productName = 'Subscription no signup fee product';
const productSlug = 'subscription-no-signup-fee-product';
const customerBilling = config.get(
	'addresses.subscriptions-customer.billing'
);
let orderId;

describeif( RUN_SUBSCRIPTIONS_TESTS )(
	'Subscriptions > Purchase subscription without signup fee (free trial)',
	() => {
		beforeAll( async () => {
			// Delete the user, if present
			await withRestApi.deleteCustomerByEmail( customerBilling.email );
		} );

		it( 'should be able to purchase a subscription with signup fee', async () => {
			// Open the subscription product
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

			await shopper.logout();
		} );

		it( 'should have a charge for subscription cost without fee & an active subscription', async () => {
			await merchant.login();

			await merchant.goToOrder( orderId );

			// Pull out and follow the link to avoid working in multiple tabs
			const paymentDetailsLink = await page.$eval(
				'p.order_number > a',
				( anchor ) => anchor.getAttribute( 'href' )
			);
			await Promise.all( [
				page.goto( paymentDetailsLink, {
					waitUntil: 'networkidle0',
				} ),
				uiLoaded(),
			] );

			await expect( page ).toMatchElement(
				'li.woocommerce-timeline-item',
				{
					text: 'A payment of $9.99 was successfully charged.',
				}
			);

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

			await merchant.logout();
		} );
	}
);
