/**
 * External dependencies
 */
import { Page } from '@playwright/test';
import qit from '@qit/helpers';

/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import { config } from '../../../config/default';
import {
	emptyCart,
	fillCardDetails,
	focusPlaceOrderButton,
	placeOrder,
	setupProductCheckout,
} from '../../../utils/shopper';
import { goToSubscriptions } from '../../../utils/shopper-navigation';

const navigateToSubscriptionDetails = async (
	page: Page,
	subscriptionId: string
) => {
	await goToSubscriptions( page );
	await page
		.getByLabel( `View subscription number ${ subscriptionId }` )
		.click();

	await page.getByRole( 'button', { name: 'Change payment' } ).click();

	await expect(
		page.getByRole( 'heading', {
			name: 'Change payment method',
		} )
	).toBeVisible();

	await page.getByText( 'Choose a new payment method' ).isVisible();
};

const customerBillingAddress =
	config.addresses[ 'subscriptions-customer' ].billing;

test.describe(
	'Subscriptions > Shopper manage payment methods',
	{ tag: [ '@shopper', '@subscriptions', '@critical' ] },
	() => {
		let subscriptionId: string;

		test.beforeAll( async ( { customerPage } ) => {
			// Delete existing subscriptions customer if exists to ensure clean state
			try {
				await qit.wp(
					`user delete $( wp user get ${ customerBillingAddress.email } --field=ID 2>/dev/null ) --yes 2>/dev/null || true`,
					true
				);
			} catch ( e ) {
				// User might not exist, continue
			}

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
				customerPage.getByText(
					'Payment method updated for all your current subscriptions.'
				)
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
				customerPage.getByText(
					'Payment method updated for all your current subscriptions.'
				)
			).toBeVisible();

			await expect(
				customerPage.getByText( 'Visa ending in 4242 (expires 02/45)' )
			).toBeVisible();
		} );
	}
);
