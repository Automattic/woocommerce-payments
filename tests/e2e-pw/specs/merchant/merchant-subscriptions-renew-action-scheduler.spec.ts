/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { describeif, getMerchant, getShopper } from '../../utils/helpers';
import * as shopper from '../../utils/shopper';
import { config } from '../../config/default';
import {
	products,
	shouldRunActionSchedulerTests,
	shouldRunSubscriptionsTests,
} from '../../utils/constants';
import {
	goToActionScheduler,
	goToSubscriptions,
} from '../../utils/merchant-navigation';

// Run the tests if the two 'skip' environment variables are not set.
describeif( shouldRunSubscriptionsTests && shouldRunActionSchedulerTests )(
	'Subscriptions > Renew a subscription via Action Scheduler',
	() => {
		const actionSchedulerHook =
			'woocommerce_scheduled_subscription_payment';

		const customerBillingConfig =
			config.addresses[ 'subscriptions-customer' ].billing;

		let subscriptionId: string;

		test.beforeAll( async ( { browser }, { project } ) => {
			const { shopperPage } = await getShopper(
				browser,
				true,
				project.use.baseURL
			);

			await shopper.addCartProduct(
				shopperPage,
				products.SUBSCRIPTION_SIGNUP_FEE
			);
			await shopper.setupCheckout( shopperPage, customerBillingConfig );
			await shopper.fillCardDetails( shopperPage, config.cards.basic );
			await shopper.placeOrder( shopperPage );
			await expect(
				shopperPage.getByRole( 'heading', { name: 'Order received' } )
			).toBeVisible();

			subscriptionId = await shopperPage
				.getByLabel( 'View subscription number' )
				.innerText();
		} );

		test( 'should renew a subscription with action scheduler', async ( {
			browser,
		} ) => {
			const { merchantPage } = await getMerchant( browser );
			// Go to Action Scheduler
			await goToActionScheduler( merchantPage, 'pending' );

			await merchantPage
				.getByLabel( 'Search hook, args and claim' )
				.fill( actionSchedulerHook );

			await merchantPage
				.getByRole( 'button', {
					name: 'Search hook, args and claim ID',
				} )
				.click();

			await merchantPage.getByRole( 'link', { name: 'Run' } ).focus();
			await merchantPage.getByRole( 'link', { name: 'Run' } ).click();

			await expect(
				merchantPage.getByText( actionSchedulerHook, { exact: true } )
			).toBeVisible();

			// Go to Subscriptions and verify the subscription renewal
			await goToSubscriptions( merchantPage );

			const numericSubscriptionId = subscriptionId.substring( 1 );

			await expect(
				merchantPage
					.locator( `#order-${ numericSubscriptionId }` )
					.getByRole( 'cell', { name: '2', exact: true } )
			).toBeVisible();
		} );
	}
);
