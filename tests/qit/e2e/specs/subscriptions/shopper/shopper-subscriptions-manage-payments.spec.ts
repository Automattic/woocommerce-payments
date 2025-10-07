/**
 * External dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import type { Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import {
	fillCardDetails,
	focusPlaceOrderButton,
	placeOrderWithOptions,
} from '../../../utils/shopper';
import { goToSubscriptions } from '../../../utils/shopper-navigation';

// Define subscriptions test guard from legacy pattern
const shouldRunSubscriptionsTests =
	process.env.SKIP_WC_SUBSCRIPTIONS_TESTS !== '1';

/**
 * Navigate to the subscription change payment method page.
 *
 * @param {Page} page The Playwright page object.
 * @param {string} subscriptionId The subscription ID.
 */
const navigateToSubscriptionDetails = async (
	page: Page,
	subscriptionId: string
) => {
	await goToSubscriptions( page );
	await page
		.getByLabel( `View subscription number ${ subscriptionId }` )
		.click();

	await page.getByRole( 'link', { name: 'Change payment' } ).click();

	await expect(
		page.getByRole( 'heading', {
			name: 'Change payment method',
		} )
	).toBeVisible();

	await expect(
		page.getByText( 'Choose a new payment method' )
	).toBeVisible();
};

test.describe( 'Subscriptions > Manage payment methods', () => {
	test.skip(
		! shouldRunSubscriptionsTests,
		'Subscriptions tests are disabled'
	);

	const customerBillingAddress =
		config.addresses[ 'subscriptions-customer' ].billing;

	test(
		'should change a default payment method to a new one',
		{ tag: [ '@critical', '@subscriptions', '@shopper' ] },
		async ( { customerPage } ) => {
			// Purchase a subscription first
			await placeOrderWithOptions( customerPage, {
				product: config.products.subscription_no_signup_fee,
				billingAddress: customerBillingAddress,
			} );

			// Extract subscription ID from the order confirmation page
			const subscriptionId = (
				await customerPage
					.getByLabel( 'View subscription number' )
					.innerText()
			 )
				.trim()
				.replace( '#', '' );

			// Navigate to change payment method page
			await navigateToSubscriptionDetails( customerPage, subscriptionId );

			// Select "Use a new payment method" option
			await customerPage.getByLabel( 'Use a new payment method' ).check();

			// Fill in new card details
			await fillCardDetails( customerPage, config.cards.basic2 );

			// Focus and submit the form - for subscription payment changes, we just click
			await focusPlaceOrderButton( customerPage );
			await customerPage.locator( '#place_order' ).click();

			// Wait for navigation back to subscription page
			await customerPage.waitForURL(
				/\/my-account\/view-subscription\//
			);
			await customerPage.waitForLoadState( 'networkidle' );

			// Verify success message - can be in different notice containers
			await expect(
				customerPage
					.locator(
						'.woocommerce-message, .woocommerce-notice--success'
					)
					.filter( { hasText: 'Payment method updated' } )
			).toBeVisible();

			// Verify we're back on the subscription view page
			await expect(
				customerPage.getByRole( 'heading', {
					name: `Subscription #${ subscriptionId }`,
				} )
			).toBeVisible();
		}
	);

	test(
		'should set a payment method to an already saved card',
		{ tag: [ '@critical', '@subscriptions', '@shopper' ] },
		async ( { customerPage } ) => {
			// Purchase a subscription first
			await placeOrderWithOptions( customerPage, {
				product: config.products.subscription_no_signup_fee,
				billingAddress: customerBillingAddress,
			} );

			// Extract subscription ID from the order confirmation page
			const subscriptionId = (
				await customerPage
					.getByLabel( 'View subscription number' )
					.innerText()
			 )
				.trim()
				.replace( '#', '' );

			// Navigate to change payment method page
			await navigateToSubscriptionDetails( customerPage, subscriptionId );

			// The first saved card should already be selected
			// Focus and submit the form to use the already saved card
			await focusPlaceOrderButton( customerPage );
			await customerPage.locator( '#place_order' ).click();

			// Wait for navigation back to subscription page
			await customerPage.waitForURL(
				/\/my-account\/view-subscription\//
			);
			await customerPage.waitForLoadState( 'networkidle' );

			// Verify success message - can be in different notice containers
			await expect(
				customerPage
					.locator(
						'.woocommerce-message, .woocommerce-notice--success'
					)
					.filter( { hasText: 'Payment method updated' } )
			).toBeVisible();

			// Verify we're back on the subscription view page
			await expect(
				customerPage.getByRole( 'heading', {
					name: `Subscription #${ subscriptionId }`,
				} )
			).toBeVisible();
		}
	);
} );
