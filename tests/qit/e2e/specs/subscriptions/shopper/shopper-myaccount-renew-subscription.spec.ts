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
	focusPlaceOrderButton,
	placeOrder,
	setupCheckout,
} from '../../../utils/shopper';
import {
	goToProductPageBySlug,
	goToSubscriptions,
} from '../../../utils/shopper-navigation';

test.describe(
	'Subscriptions > Renew a subscription in my account',
	{ tag: [ '@critical', '@subscriptions', '@shopper' ] },
	() => {
		const customerBillingAddress =
			config.addresses[ 'subscriptions-customer' ].billing;

		let subscriptionId: string;

		test( 'should be able to purchase a subscription', async ( {
			customerPage,
		} ) => {
			// Navigate directly to the subscription product page
			await goToProductPageBySlug(
				customerPage,
				'subscription-signup-fee-product'
			);

			// Add to cart from product page - target the main product form
			const addToCartButton = customerPage
				.locator( '.summary.entry-summary' )
				.getByRole( 'button', {
					name: /Sign up now|Add to cart/i,
					exact: false,
				} );

			await addToCartButton.click();

			// Wait for product to be added
			await expect(
				customerPage.getByText( /has been added to your cart/i )
			).toBeVisible();

			// Proceed to checkout
			await setupCheckout( customerPage, customerBillingAddress );

			// Fill card details
			await fillCardDetails( customerPage, config.cards.basic );

			// Place order
			await focusPlaceOrderButton( customerPage );
			await placeOrder( customerPage );

			// Wait for order confirmation
			await expect(
				customerPage.getByRole( 'heading', {
					name: 'Order received',
				} )
			).toBeVisible();

			// Extract subscription ID from the order confirmation page
			subscriptionId = await customerPage
				.getByLabel( 'View subscription number' )
				.innerText();
		} );

		test( 'should be able to renew a subscription in my account', async ( {
			customerPage,
		} ) => {
			await goToSubscriptions( customerPage );

			if ( ! subscriptionId ) {
				throw new Error( 'Subscription ID is not set' );
			}

			const numericSubscriptionId = subscriptionId.substring( 1 );

			await customerPage
				.getByLabel(
					`View subscription number ${ numericSubscriptionId }`
				)
				.click();

			await customerPage.getByText( 'Renew now' ).click();
			await expect(
				customerPage.getByText( 'Complete checkout to renew now.' )
			).toBeVisible();
			await focusPlaceOrderButton( customerPage );
			await placeOrder( customerPage );
			await customerPage.waitForURL( /\/order-received\// );
			await expect(
				customerPage.getByRole( 'heading', { name: 'Order received' } )
			).toBeVisible();
		} );
	}
);
