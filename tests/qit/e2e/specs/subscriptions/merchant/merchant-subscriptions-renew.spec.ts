/**
 * External dependencies
 */
import { test, expect } from '../../../fixtures/auth';

/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import {
	emptyCart,
	fillCardDetails,
	focusPlaceOrderButton,
	placeOrder,
	setupProductCheckout,
} from '../../../utils/shopper';
import { goToSubscriptions, dataHasLoaded } from '../../../utils/merchant';

// Define subscriptions test guard from legacy pattern
const shouldRunSubscriptionsTests =
	process.env.SKIP_WC_SUBSCRIPTIONS_TESTS !== '1';

test.describe(
	'Subscriptions > Renew a subscription as a merchant',
	{ tag: [ '@critical', '@subscriptions', '@merchant' ] },
	() => {
		test.skip(
			! shouldRunSubscriptionsTests,
			'Subscriptions tests are disabled'
		);

		const customerBillingConfig =
			config.addresses[ 'subscriptions-customer' ].billing;

		test( 'should be able to renew a subscription', async ( {
			customerPage,
			adminPage,
		} ) => {
			// Step 1: Customer creates a subscription
			await emptyCart( customerPage );
			await setupProductCheckout(
				customerPage,
				[ [ config.products.subscription_signup_fee, 1 ] ],
				customerBillingConfig
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

			// Extract subscription ID from the order confirmation page
			const subscriptionId = (
				await customerPage
					.getByRole( 'link', { name: 'View subscription number' } )
					.textContent()
			 )
				.trim()
				.replace( '#', '' );

			// Step 2: Merchant navigates to the subscription page
			await goToSubscriptions( adminPage );
			await adminPage
				.getByRole( 'link', { name: `#${ subscriptionId }` } )
				.click();
			await dataHasLoaded( adminPage );

			await expect(
				adminPage.getByRole( 'heading', {
					name: 'Edit Subscription',
				} )
			).toBeVisible();

			// Step 3: Merchant processes renewal
			const orderActions = adminPage.locator(
				'select[name="wc_order_action"]'
			);
			await orderActions.selectOption( { label: 'Process renewal' } );

			// Prepare to accept the dialog before clicking the submit button
			adminPage.on( 'dialog', async ( dialog ) => {
				await dialog.accept();
			} );

			await adminPage
				.locator( '#actions' )
				.getByRole( 'button', { name: /Apply.+/i } )
				.click();
			await adminPage.waitForLoadState( 'networkidle' );

			// Step 4: Verify renewal order was created
			await expect(
				adminPage.getByRole( 'cell', { name: 'Renewal Order' } )
			).toBeVisible();
		} );
	}
);
