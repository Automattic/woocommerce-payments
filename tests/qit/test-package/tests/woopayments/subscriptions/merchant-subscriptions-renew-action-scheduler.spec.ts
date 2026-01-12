/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import { config } from '../../../config/default';
import {
	addSubscriptionToCart,
	emptyCart,
	setupCheckout,
	fillCardDetails,
	placeOrder,
} from '../../../utils/shopper';
import {
	goToActionScheduler,
	goToSubscriptions,
} from '../../../utils/merchant';

test.describe(
	'Subscriptions > Renew a subscription via Action Scheduler',
	{ tag: [ '@merchant', '@subscriptions', '@critical' ] },
	() => {
		const actionSchedulerHook =
			'woocommerce_scheduled_subscription_payment';

		const customerBillingConfig =
			config.addresses[ 'subscriptions-customer' ].billing;

		test.beforeAll( async ( { customerPage } ) => {
			await emptyCart( customerPage );
			await addSubscriptionToCart(
				customerPage,
				config.products.subscription_signup_fee
			);
			await setupCheckout( customerPage, customerBillingConfig );
			await fillCardDetails( customerPage, config.cards.basic );
			await placeOrder( customerPage );
			await expect(
				customerPage.getByRole( 'heading', { name: 'Order received' } )
			).toBeVisible();
		} );

		test( 'should renew a subscription with action scheduler', async ( {
			adminPage,
		} ) => {
			// Go to Action Scheduler
			await goToActionScheduler( adminPage, 'pending' );

			await adminPage
				.getByLabel( 'Search hook, args and claim' )
				.fill( actionSchedulerHook );

			await adminPage
				.getByRole( 'button', {
					name: 'Search hook, args and claim ID',
				} )
				.click();

			await adminPage.getByRole( 'link', { name: 'Run' } ).focus();
			await adminPage.getByRole( 'link', { name: 'Run' } ).click();

			await expect(
				adminPage.getByText( actionSchedulerHook, { exact: true } )
			).toBeVisible();

			// Go to Subscriptions and verify the subscription renewal.
			// After renewal, the "Orders" column should show "2" (initial order + renewal order).
			await goToSubscriptions( adminPage );

			await expect(
				adminPage.getByRole( 'cell', { name: '2', exact: true } )
			).toBeVisible();
		} );
	}
);
