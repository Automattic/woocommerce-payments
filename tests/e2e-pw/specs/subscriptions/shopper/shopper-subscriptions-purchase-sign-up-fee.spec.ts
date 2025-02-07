/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import { describeif, getMerchant, getShopper } from '../../../utils/helpers';
import * as shopper from '../../../utils/shopper';
import * as navigation from '../../../utils/merchant-navigation';
import { shouldRunSubscriptionsTests } from '../../../utils/constants';

describeif( shouldRunSubscriptionsTests )(
	'Subscriptions > Purchase subscription with signup fee',
	() => {
		let merchantPage: Page;
		let shopperPage: Page;
		let orderId: string;

		const customerBillingAddress =
			config.addresses[ 'subscriptions-customer' ].billing;

		test.beforeAll( async ( { browser }, { project } ) => {
			merchantPage = ( await getMerchant( browser ) ).merchantPage;
			shopperPage = (
				await getShopper( browser, true, project.use.baseURL )
			 ).shopperPage;
		} );

		test( 'should be able to purchase a subscription with signup fee', async () => {
			orderId = await shopper.placeOrderWithOptions( shopperPage, {
				product: config.products.subscription_signup_fee,
				billingAddress: customerBillingAddress,
			} );
		} );

		test( 'should have a charge for subscription cost with fee & an active subscription', async () => {
			await navigation.goToOrder( merchantPage, orderId );

			const paymentIntentId = await merchantPage
				.locator( '#order_data' )
				.getByRole( 'link', {
					name: /pi_/,
				} )
				.innerText();

			// Verify we have an active subscription
			await expect(
				merchantPage.getByRole( 'row', {
					name: /Subscription .+ Active/,
				} )
			).toBeVisible();

			await navigation.goToPaymentDetails(
				merchantPage,
				paymentIntentId
			);

			await expect(
				merchantPage.getByText(
					/A payment of \$11\.98( USD)? was successfully charged./
				)
			).toBeVisible();
		} );
	}
);
