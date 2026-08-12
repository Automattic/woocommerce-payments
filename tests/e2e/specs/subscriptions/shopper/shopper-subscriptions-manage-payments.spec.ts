/**
 * External dependencies
 */
import test, { expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import { shouldRunSubscriptionsTests } from '../../../utils/constants';
import { describeif, getShopper } from '../../../utils/helpers';
import * as shopper from '../../../utils/shopper';
import * as navigation from '../../../utils/shopper-navigation';

const changePaymentMethodButtonSelector =
	'button[name="woocommerce_change_payment"], #place_order';

// Target the subscription action by the query arg the change-payment endpoint reads.
// Subscriptions renders these actions as anchors styled as buttons, so matching on an
// ARIA role couples the test to markup that has already changed once upstream.
const changePaymentMethodActionSelector = 'a[href*="change_payment_method="]';

const navigateToSubscriptionDetails = async (
	page: Page,
	subscriptionId: string
) => {
	await navigation.goToSubscriptions( page );
	await page
		.getByLabel( `View subscription number ${ subscriptionId }` )
		.click();

	await page.locator( changePaymentMethodActionSelector ).first().click();

	await expect(
		page.getByRole( 'heading', {
			name: 'Change payment method',
		} )
	).toBeVisible();

	await page.getByText( 'Choose a new payment method' ).isVisible();
};

describeif( shouldRunSubscriptionsTests )(
	'Shopper > Subscriptions > Manage Payment Methods',
	{ tag: '@critical' },
	() => {
		let page: Page;
		let subscriptionId: string;

		const customerBillingAddress =
			config.addresses[ 'subscriptions-customer' ].billing;

		test.beforeAll( async ( { browser }, { project } ) => {
			page = ( await getShopper( browser, true, project.use.baseURL ) )
				.shopperPage;

			// Purchase a subscription.
			await shopper.placeOrderWithOptions( page, {
				product: config.products.subscription_no_signup_fee,
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
			await page
				.locator( changePaymentMethodButtonSelector )
				.first()
				.click( { noWaitAfter: true } );

			await expect(
				page.getByText( 'Payment method updated.' )
			).toBeVisible();

			await expect(
				page.getByText( 'Visa ending in 1111 (expires 11/45)' )
			).toBeVisible();
		} );

		test( 'should set a payment method to an already saved card', async () => {
			await shopper.focusPlaceOrderButton( page );
			await page
				.locator( changePaymentMethodButtonSelector )
				.first()
				.click( { noWaitAfter: true } );

			await expect(
				page.getByText( 'Payment method updated.' )
			).toBeVisible();

			await expect(
				page.getByText( 'Visa ending in 4242 (expires 02/45)' )
			).toBeVisible();
		} );
	}
);
