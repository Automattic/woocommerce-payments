/**
 * External dependencies
 */
import { test, expect, getAuthState } from '../../../fixtures/auth';
import type { BrowserContext, Page } from '@playwright/test';

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

	let shopperContext: BrowserContext;
	let shopperPage: Page;
	let subscriptionId: string;

	const customerBillingAddress =
		config.addresses[ 'subscriptions-customer' ].billing;

	test.beforeAll( async ( { browser } ) => {
		// Create a new context for the shopper
		shopperContext = await browser.newContext( {
			storageState: await getAuthState( browser, 'customer' ),
		} );
		shopperPage = await shopperContext.newPage();

		// Purchase a subscription
		await placeOrderWithOptions( shopperPage, {
			product: config.products.subscription_no_signup_fee,
			billingAddress: customerBillingAddress,
		} );

		// Extract subscription ID from the order confirmation page
		subscriptionId = (
			await shopperPage
				.getByLabel( 'View subscription number' )
				.innerText()
		 )
			.trim()
			.replace( '#', '' );
	} );

	test.afterAll( async () => {
		await shopperContext?.close();
	} );

	test.beforeEach( async () => {
		await navigateToSubscriptionDetails( shopperPage, subscriptionId );
	} );

	test(
		'should change a default payment method to a new one',
		{ tag: [ '@critical', '@subscriptions', '@shopper' ] },
		async () => {
			// Select "Use a new payment method" option
			await shopperPage.getByLabel( 'Use a new payment method' ).check();

			// Fill in new card details
			await fillCardDetails( shopperPage, config.cards.basic2 );

			// Focus and submit the form - for subscription payment changes, we just click
			await focusPlaceOrderButton( shopperPage );
			await shopperPage.locator( '#place_order' ).click();

			// Wait for navigation back to subscription page
			await shopperPage.waitForURL( /\/my-account\/view-subscription\// );
			await shopperPage.waitForLoadState( 'networkidle' );

			// Verify success message - can be in different notice containers
			await expect(
				shopperPage
					.locator(
						'.woocommerce-message, .woocommerce-notice--success'
					)
					.filter( { hasText: 'Payment method updated' } )
			).toBeVisible();

			// Verify we're back on the subscription view page
			await expect(
				shopperPage.getByRole( 'heading', {
					name: `Subscription #${ subscriptionId }`,
				} )
			).toBeVisible();
		}
	);

	test(
		'should set a payment method to an already saved card',
		{ tag: [ '@critical', '@subscriptions', '@shopper' ] },
		async () => {
			// The first saved card should already be selected
			// Focus and submit the form to use the already saved card
			await focusPlaceOrderButton( shopperPage );
			await shopperPage.locator( '#place_order' ).click();

			// Wait for navigation back to subscription page
			await shopperPage.waitForURL( /\/my-account\/view-subscription\// );
			await shopperPage.waitForLoadState( 'networkidle' );

			// Verify success message - can be in different notice containers
			await expect(
				shopperPage
					.locator(
						'.woocommerce-message, .woocommerce-notice--success'
					)
					.filter( { hasText: 'Payment method updated' } )
			).toBeVisible();

			// Verify we're back on the subscription view page
			await expect(
				shopperPage.getByRole( 'heading', {
					name: `Subscription #${ subscriptionId }`,
				} )
			).toBeVisible();
		}
	);
} );
