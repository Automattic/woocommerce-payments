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
	emptyCart,
	fillCardDetails,
	placeOrder,
	setupProductCheckout,
} from '../../../utils/shopper';
import { goToSubscriptions } from '../../../utils/shopper-navigation';

// Define subscriptions test guard from legacy pattern
const shouldRunSubscriptionsTests =
	process.env.SKIP_WC_SUBSCRIPTIONS_TESTS !== '1';

test.describe( 'Subscriptions > Purchase multiple subscriptions', () => {
	test.skip(
		! shouldRunSubscriptionsTests,
		'Subscriptions tests are disabled'
	);

	test(
		'should be able to purchase multiple subscriptions',
		{ tag: [ '@critical', '@subscriptions', '@shopper' ] },
		async ( { customerPage } ) => {
			const customerBillingAddress = config.addresses.customer.billing;

			// Empty cart to ensure clean state
			await emptyCart( customerPage );

			// Add both subscription products to cart and proceed to checkout
			// Using setupProductCheckout which adds products from the shop page
			await setupProductCheckout(
				customerPage,
				[
					[ config.products.subscription_no_signup_fee, 1 ],
					[ config.products.subscription_signup_fee, 1 ],
				],
				customerBillingAddress,
				'USD'
			);

			// Fill card details and place order
			await fillCardDetails( customerPage, config.cards.basic );
			await placeOrder( customerPage );

			// Wait for order confirmation
			await expect(
				customerPage.getByRole( 'heading', {
					name: 'Order received',
				} )
			).toBeVisible();

			// Get the subscription ID from the order confirmation page
			const subscriptionId = (
				await customerPage
					.getByLabel( 'View subscription number' )
					.innerText()
			 )
				.trim()
				.replace( '#', '' );

			// Navigate to subscriptions page
			await goToSubscriptions( customerPage );

			// Find the subscription row by ID
			const latestSubscriptionRow = customerPage.getByRole( 'row', {
				name: `subscription number ${ subscriptionId }`,
			} );

			await expect( latestSubscriptionRow ).toBeVisible();

			// Click to view subscription details
			await latestSubscriptionRow
				.getByRole( 'link', {
					name: 'View',
				} )
				.nth( 0 )
				.click();

			await customerPage.waitForLoadState( 'networkidle' );

			// Verify the subscription details page shows both products
			// Check for the order_details table with line items
			const subTotalsRows = customerPage.locator(
				'.order_details tr.order_item'
			);

			// Verify we have 2 products in one subscription
			await expect( subTotalsRows ).toHaveCount( 2 );

			// Verify both products show $9.99/month
			for ( let i = 0; i < ( await subTotalsRows.count() ); i++ ) {
				const row = subTotalsRows.nth( i );
				await expect( row.locator( '.product-total' ) ).toContainText(
					'$9.99 / month'
				);
			}

			// Verify total recurring amount ($19.98/month for both products)
			await expect(
				customerPage
					.getByRole( 'row', { name: /total:/i } )
					.getByRole( 'cell' )
					.nth( 1 )
			).toContainText( /\$19\.98.*\/ month/i );

			// Verify related order total (recurring + signup fee)
			await expect(
				customerPage.getByText( /\$21\.97.*for 2 items/i )
			).toBeVisible();
		}
	);
} );
