/**
 * External dependencies
 */
import qit from '@qit/helpers';

/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import { config } from '../../../config/default';
import {
	emptyCart,
	fillCardDetails,
	focusPlaceOrderButton,
	placeOrder,
	setupProductCheckout,
} from '../../../utils/shopper';
import { goToOrder, goToPaymentDetails } from '../../../utils/merchant';

const customerBillingAddress =
	config.addresses[ 'subscriptions-customer' ].billing;

test.describe(
	'Subscriptions > Purchase subscription with signup fee',
	{ tag: [ '@shopper', '@subscriptions', '@critical' ] },
	() => {
		let orderId: string;

		test.beforeAll( async ( { customerPage } ) => {
			// Delete existing subscriptions customer if exists to ensure clean state
			try {
				await qit.wp(
					`user delete $( wp user get ${ customerBillingAddress.email } --field=ID 2>/dev/null ) --yes 2>/dev/null || true`,
					true
				);
			} catch ( e ) {
				// User might not exist, continue
			}

			// Purchase the subscription with signup fee
			await emptyCart( customerPage );
			await setupProductCheckout(
				customerPage,
				[ [ config.products.subscription_signup_fee, 1 ] ],
				customerBillingAddress
			);
			await fillCardDetails( customerPage, config.cards.basic );
			await focusPlaceOrderButton( customerPage );
			await placeOrder( customerPage );
			await customerPage.waitForURL( /\/order-received\//, {
				waitUntil: 'load',
			} );
			await expect(
				customerPage.getByRole( 'heading', { name: 'Order received' } )
			).toBeVisible();

			// Get the order ID from the URL
			const url = customerPage.url();
			orderId = url.match( /\/order-received\/(\d+)\// )?.[ 1 ] ?? '';
		} );

		test( 'should have a charge for subscription cost with fee & an active subscription', async ( {
			adminPage,
		} ) => {
			await goToOrder( adminPage, orderId );

			const paymentIntentId = await adminPage
				.locator( '#order_data' )
				.getByRole( 'link', {
					name: /pi_/,
				} )
				.innerText();

			// Verify we have an active subscription
			await expect(
				adminPage.getByRole( 'row', {
					name: /Subscription .+ Active/,
				} )
			).toBeVisible();

			await goToPaymentDetails( adminPage, paymentIntentId );

			await expect(
				adminPage.getByText(
					/A payment of \$11\.98( USD)? was successfully charged./
				)
			).toBeVisible();
		} );
	}
);
