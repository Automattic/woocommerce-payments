/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	describeif,
	getAnonymousShopper,
	getMerchant,
	useMerchant,
} from '../../utils/helpers';
import * as shopper from '../../utils/shopper';
import { config } from '../../config/default';
import {
	products,
	shouldRunActionSchedulerTests,
	shouldRunSubscriptionsTests,
} from '../../utils/constants';
import RestAPI from '../../utils/rest-api';
import {
	goToActionScheduler,
	goToSubscriptions,
} from '../../utils/merchant-navigation';

// Run the tests if the two 'skip' environment variables are not set.
describeif( shouldRunSubscriptionsTests && shouldRunActionSchedulerTests )(
	'Subscriptions > Renew a subscription via Action Scheduler',
	() => {
		useMerchant();

		const actionSchedulerHook =
			'woocommerce_scheduled_subscription_payment';

		const customerBillingConfig =
			config.addresses[ 'subscriptions-customer' ].billing;

		let subscriptionId: string;
		let page: Page;

		test.beforeAll( async ( { browser }, { project } ) => {
			const restApi = new RestAPI( project.use.baseURL );
			await restApi.deleteCustomerByEmailAddress(
				customerBillingConfig.email
			);

			const { shopperPage } = await getAnonymousShopper( browser );
			page = shopperPage;

			await shopper.addCartProduct(
				page,
				products.SUBSCRIPTION_SIGNUP_FEE
			);
			await shopper.setupCheckout( page, customerBillingConfig );
			await shopper.fillCardDetails( page, config.cards.basic );
			await shopper.placeOrder( page );
			await expect(
				page.getByRole( 'heading', { name: 'Order received' } )
			).toBeVisible();

			subscriptionId = await page
				.getByLabel( 'View subscription number' )
				.innerText();

			const { merchantPage } = await getMerchant( browser );
			page = merchantPage;
		} );

		test( 'should renew a subscription with action scheduler', async () => {
			// Go to Action Scheduler
			await goToActionScheduler( page, 'pending' );

			await page
				.getByLabel( 'Search hook, args and claim' )
				.fill( actionSchedulerHook );

			await page
				.getByRole( 'button', {
					name: 'Search hook, args and claim ID',
				} )
				.click();

			await page.getByRole( 'link', { name: 'Run' } ).focus();
			await page.getByRole( 'link', { name: 'Run' } ).click();

			await expect(
				page.getByText( actionSchedulerHook, { exact: true } )
			).toBeVisible();

			// Go to Subscriptions and verify the subscription renewal
			await goToSubscriptions( page );

			const numericSubscriptionId = subscriptionId.substring( 1 );

			await expect(
				page
					.locator( `#order-${ numericSubscriptionId }` )
					.getByRole( 'cell', { name: '2', exact: true } )
			).toBeVisible();
		} );
	}
);
