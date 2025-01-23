/**
 * External dependencies
 */
import test, { expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import RestAPI from '../../utils/rest-api';
import { config } from '../../config/default';
import { products, shouldRunSubscriptionsTests } from '../../utils/constants';
import { describeif, getAnonymousShopper } from '../../utils/helpers';
import * as shopper from '../../utils/shopper';
import * as navigation from '../../utils/shopper-navigation';

const navigateToSubscriptionDetails = async (
	page: Page,
	subscriptionId: string
) => {
	await navigation.goToSubscriptions( page );
	await page
		.getByLabel( `View subscription number ${ subscriptionId }` )
		.click();

	await page.getByRole( 'link', { name: 'Change payment' } ).click();

	await expect(
		page.getByRole( 'heading', {
			name: 'Change payment method',
		} )
	).toBeVisible();

	await page.getByText( 'Choose a new payment method' ).isVisible();
};

describeif( shouldRunSubscriptionsTests )(
	'Shopper > Subscriptions > Manage Payment Methods',
	() => {
		let page: Page;
		let subscriptionId: string;

		const customerBillingAddress =
			config.addresses[ 'subscriptions-customer' ].billing;

		test.beforeAll( async ( { browser }, { project } ) => {
			// Delete the user, if present.
			const restApi = new RestAPI( project.use.baseURL );
			await restApi.deleteCustomerByEmailAddress(
				customerBillingAddress.email
			);

			page = ( await getAnonymousShopper( browser ) ).shopperPage;

			// Purchase a subscription.
			await shopper.placeOrderWithOptions( page, {
				productId: products.SUBSCRIPTION_NO_SIGNUP_FEE,
				billingAddress: customerBillingAddress,
			} );

			subscriptionId = (
				await page.getByLabel( 'View subscription number' ).innerText()
			 ).substring( 1 );
		} );

		test.beforeEach( async () => {
			await navigateToSubscriptionDetails( page, subscriptionId );
		} );

		test( 'should change a default payment method to a new one', async () => {
			await page.getByLabel( 'Use a new payment method' ).check();
			await shopper.fillCardDetails( page, config.cards.basic2 );
			await shopper.focusPlaceOrderButton( page );
			await shopper.placeOrder( page );

			await expect(
				page.getByText( 'Payment method updated.' )
			).toBeVisible();

			await expect(
				page.getByText( 'Visa ending in 1111 (expires 11/45)' )
			).toBeVisible();
		} );

		test( 'should set a payment method to an already saved card', async () => {
			await shopper.focusPlaceOrderButton( page );
			await page.locator( '#place_order' ).click();

			await expect(
				page.getByText( 'Payment method updated.' )
			).toBeVisible();

			await expect(
				page.getByText( 'Visa ending in 4242 (expires 02/45)' )
			).toBeVisible();
		} );
	}
);
