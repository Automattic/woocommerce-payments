/**
 * External dependencies
 */
import { Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { test, expect, recreateCustomer } from '../../../fixtures/auth';
import { config } from '../../../config/default';
import {
	emptyCart,
	fillCardDetails,
	focusPlaceOrderButton,
	placeOrder,
	setupProductCheckout,
} from '../../../utils/shopper';
import { goToSubscriptions } from '../../../utils/shopper-navigation';

// Target the subscription action by the query arg the change-payment endpoint reads.
// Subscriptions renders these actions as anchors styled as buttons, so matching on an
// ARIA role couples the test to markup that has already changed once upstream.
const changePaymentMethodActionSelector = 'a[href*="change_payment_method="]';

const navigateToSubscriptionDetails = async (
	page: Page,
	subscriptionId: string
) => {
	await goToSubscriptions( page );
	await page
		.getByLabel( `View subscription number ${ subscriptionId }` )
		.click();

	await page.locator( changePaymentMethodActionSelector ).first().click();

	await expect(
		page.getByRole( 'heading', {
			name: 'Change payment method',
		} )
	).toBeVisible();

	await expect(
		page.getByText( 'Choose a new payment method' )
	).toBeVisible();
};

const customerBillingAddress =
	config.addresses[ 'subscriptions-customer' ].billing;

test.describe(
	'Subscriptions > Shopper manage payment methods',
	{ tag: [ '@shopper', '@subscriptions', '@critical' ] },
	() => {
		let subscriptionId: string;

		test.beforeAll( async () => {
			await recreateCustomer();
		} );

		test.beforeAll( async ( { customerPage } ) => {
			// Purchase a subscription.
			await emptyCart( customerPage );
			await setupProductCheckout(
				customerPage,
				[ [ config.products.subscription_no_signup_fee, 1 ] ],
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

			subscriptionId = (
				await customerPage
					.getByLabel( 'View subscription number' )
					.innerText()
			).substring( 1 );
		} );

		test( 'should change a default payment method to a new one', async ( {
			customerPage,
		} ) => {
			await navigateToSubscriptionDetails( customerPage, subscriptionId );

			await customerPage.getByLabel( 'Use a new payment method' ).check();
			await fillCardDetails( customerPage, config.cards.basic2 );
			await focusPlaceOrderButton( customerPage );
			await placeOrder( customerPage );

			await expect(
				customerPage.getByText( 'Payment method updated.' )
			).toBeVisible();

			await expect(
				customerPage.getByText( 'Visa ending in 1111 (expires 11/45)' )
			).toBeVisible();
		} );

		test( 'should set a payment method to an already saved card', async ( {
			customerPage,
		} ) => {
			await navigateToSubscriptionDetails( customerPage, subscriptionId );

			await focusPlaceOrderButton( customerPage );
			await customerPage.locator( '#place_order' ).click();

			await expect(
				customerPage.getByText( 'Payment method updated.' )
			).toBeVisible();

			await expect(
				customerPage.getByText( 'Visa ending in 4242 (expires 02/45)' )
			).toBeVisible();
		} );
	}
);
