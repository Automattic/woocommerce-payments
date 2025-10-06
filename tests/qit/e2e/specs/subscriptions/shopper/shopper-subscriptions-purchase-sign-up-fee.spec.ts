/**
 * External dependencies
 */
import { expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { test } from '../../../fixtures/auth';
import { config } from '../../../config/default';
import {
	fillCardDetails,
	placeOrder,
	setupCheckout,
} from '../../../utils/shopper';
import { goToProductPageBySlug } from '../../../utils/shopper-navigation';
import { goToOrder, goToPaymentDetails } from '../../../utils/merchant';

// Define subscriptions test guard from legacy pattern
const shouldRunSubscriptionsTests =
	process.env.SKIP_WC_SUBSCRIPTIONS_TESTS !== '1';

test.describe( 'Subscriptions > Purchase subscription with signup fee', () => {
	test.skip(
		! shouldRunSubscriptionsTests,
		'Subscriptions tests are disabled'
	);

	let orderId: string;

	const customerBillingAddress =
		config.addresses[ 'subscriptions-customer' ].billing;

	test(
		'should be able to purchase a subscription with signup fee',
		{ tag: [ '@critical', '@subscriptions', '@shopper' ] },
		async ( { customerPage } ) => {
			// Navigate directly to the subscription product page
			await goToProductPageBySlug(
				customerPage,
				'subscription-signup-fee-product'
			);

			// Add to cart from product page - target the main product form, not sidebar widgets
			// Subscription products may have "Sign up now" button instead of "Add to cart"
			const addToCartButton = customerPage
				.locator( '.summary.entry-summary' )
				.getByRole( 'button', {
					name: /Sign up now|Add to cart/i,
					exact: false,
				} );

			await addToCartButton.click();

			// Wait for product to be added - check for success message
			await expect(
				customerPage.getByText( /has been added to your cart/i )
			).toBeVisible();

			// Proceed to checkout
			await setupCheckout( customerPage, customerBillingAddress );

			// Fill card details
			await fillCardDetails( customerPage, config.cards.basic );

			// Place order
			await placeOrder( customerPage );

			// Wait for order confirmation
			await expect(
				customerPage.getByRole( 'heading', {
					name: 'Order received',
				} )
			).toBeVisible();

			// Extract order ID from URL
			const url = await customerPage.url();
			orderId = url.match( /\/order-received\/(\d+)\// )?.[ 1 ] ?? '';
		}
	);

	test(
		'should have a charge for subscription cost with fee & an active subscription',
		{ tag: [ '@subscriptions', '@merchant' ] },
		async ( { adminPage } ) => {
			await goToOrder( adminPage, orderId );

			// Verify we have an active subscription in the "Related Orders" section
			// In HPOS (High-Performance Order Storage), subscriptions appear as related orders
			await expect(
				adminPage.getByRole( 'row', {
					name: /Subscription.*Active.*\$9\.99/,
				} )
			).toBeVisible();

			// Get the payment intent ID - for subscriptions with signup fee, this should be a payment intent (pi_)
			// Use .first() to handle multiple payment intent links (appears in both order data and notes)
			const paymentIntentLink = adminPage
				.getByRole( 'link', {
					name: /pi_/,
				} )
				.first();

			// Verify payment intent exists and get its ID
			await expect( paymentIntentLink ).toBeVisible();
			const paymentIntentId = await paymentIntentLink.innerText();

			// Navigate to payment details page
			await goToPaymentDetails( adminPage, paymentIntentId );

			// Verify the payment was successful with correct amount
			await expect(
				adminPage.getByText(
					/A payment of \$11\.98( USD)? was successfully charged./
				)
			).toBeVisible();
		}
	);
} );
