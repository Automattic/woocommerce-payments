/**
 * External dependencies
 */
import { test, expect } from '../../../fixtures/auth';

/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import {
	addToCartFromShopPage,
	emptyCart,
	fillCardDetails,
	placeOrder,
	setupCheckout,
} from '../../../utils/shopper';
import {
	goToActionScheduler,
	goToSubscriptions,
} from '../../../utils/merchant';

// Define subscriptions test guard from legacy pattern
const shouldRunSubscriptionsTests =
	process.env.SKIP_WC_SUBSCRIPTIONS_TESTS !== '1';

// Define action scheduler test guard
const shouldRunActionSchedulerTests =
	process.env.SKIP_ACTION_SCHEDULER_TESTS !== '1';

test.describe(
	'Subscriptions > Renew a subscription via Action Scheduler',
	{ tag: [ '@critical', '@subscriptions', '@merchant' ] },
	() => {
		test.skip(
			! shouldRunSubscriptionsTests || ! shouldRunActionSchedulerTests,
			'Subscriptions or Action Scheduler tests are disabled'
		);

		const actionSchedulerHook =
			'woocommerce_scheduled_subscription_payment';

		const customerBillingConfig =
			config.addresses[ 'subscriptions-customer' ].billing;

		test( 'should renew a subscription with action scheduler', async ( {
			customerPage,
			adminPage,
		} ) => {
			// Step 1: Customer creates a subscription
			await emptyCart( customerPage );
			await addToCartFromShopPage(
				customerPage,
				config.products.subscription_signup_fee
			);
			await setupCheckout( customerPage, customerBillingConfig );
			await fillCardDetails( customerPage, config.cards.basic );
			await placeOrder( customerPage );
			await expect(
				customerPage.getByRole( 'heading', { name: 'Order received' } )
			).toBeVisible();

			// Step 2: Merchant goes to Action Scheduler
			await goToActionScheduler( adminPage, 'pending' );

			// Search for the subscription payment hook
			await adminPage
				.getByLabel( 'Search hook, args and claim' )
				.fill( actionSchedulerHook );

			await adminPage
				.getByRole( 'button', {
					name: 'Search hook, args and claim ID',
				} )
				.click();

			// Step 3: Run the scheduled action
			await adminPage.getByRole( 'link', { name: 'Run' } ).focus();
			await adminPage.getByRole( 'link', { name: 'Run' } ).click();

			// Verify the action ran
			await expect(
				adminPage.getByText( actionSchedulerHook, { exact: true } )
			).toBeVisible();

			// Step 4: Go to Subscriptions and verify the renewal
			await goToSubscriptions( adminPage );

			// Verify that the subscription has 2 related orders now (original + renewal)
			await expect(
				adminPage.getByRole( 'cell', { name: '2', exact: true } )
			).toBeVisible();
		} );
	}
);
