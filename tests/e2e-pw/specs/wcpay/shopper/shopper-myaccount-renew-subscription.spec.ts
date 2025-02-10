/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import { describeif, getShopper } from '../../../utils/helpers';
import * as shopper from '../../../utils/shopper';
import * as navigation from '../../../utils/shopper-navigation';
import { shouldRunSubscriptionsTests } from '../../../utils/constants';

describeif( shouldRunSubscriptionsTests )(
	'Subscriptions > Renew a subscription in my account',
	() => {
		const customerBillingConfig =
			config.addresses[ 'subscriptions-customer' ].billing;

		let subscriptionId: string;
		let page: Page;

		test.beforeAll( async ( { browser }, { project } ) => {
			const { shopperPage } = await getShopper(
				browser,
				true,
				project.use.baseURL
			);
			page = shopperPage;
		} );

		test( 'should be able to purchase a subscription', async () => {
			await shopper.placeOrderWithOptions( page, {
				product: config.products.subscription_signup_fee,
				billingAddress: customerBillingConfig,
			} );

			subscriptionId = await page
				.getByLabel( 'View subscription number' )
				.innerText();
		} );

		test( 'should be able to renew a subscription in my account', async () => {
			await navigation.goToSubscriptions( page );

			if ( ! subscriptionId ) {
				throw new Error( 'Subscription ID is not set' );
			}

			const numericSubscriptionId = subscriptionId.substring( 1 );

			await page
				.getByLabel(
					`View subscription number ${ numericSubscriptionId }`
				)
				.click();

			await page.getByText( 'Renew now' ).click();
			await expect(
				page.getByText( 'Complete checkout to renew now.' )
			).toBeVisible();
			await shopper.focusPlaceOrderButton( page );
			await shopper.placeOrder( page );
			await page.waitForURL( /\/order-received\// );
			await expect(
				page.getByRole( 'heading', { name: 'Order received' } )
			).toBeVisible();
		} );
	}
);
