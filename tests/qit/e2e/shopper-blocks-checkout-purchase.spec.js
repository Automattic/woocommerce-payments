/**
 * External dependencies
 */
import { expect, test } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	cards,
	defaultBillingAddress,
	addToCartFromShopPage,
	setupCheckout,
	fillCardDetails,
	placeOrder,
	confirm3dsAuthentication,
} from './helpers/checkout.js';

test.describe(
	'QIT WooCommerce Blocks Checkout - Successful purchase @critical @blocks @shopper',
	() => {
		// Test both with and without card testing protection
		for ( const ctpEnabled of [ false, true ] ) {
			test.describe( `Card testing protection ${ ctpEnabled }`, () => {
				let customerPage;

				test.beforeAll( async ( { browser } ) => {
					// Set up customer page for shopping
					customerPage = await browser.newPage();
				} );

				test.afterAll( async () => {
					await customerPage?.close();
				} );

				test.beforeEach( async () => {
					await addToCartFromShopPage( customerPage );

					// Verify cart has items before proceeding to checkout
					await customerPage.goto( '/cart' );
					await customerPage.waitForLoadState( 'domcontentloaded' );

					// Check if cart is empty and retry adding product if needed
					const cartEmptyMessage = customerPage.locator(
						'.cart-empty, .wc-empty-cart-message'
					);
					if ( ( await cartEmptyMessage.count() ) > 0 ) {
						await addToCartFromShopPage( customerPage );
						await customerPage.goto( '/cart' );
						await customerPage.waitForLoadState(
							'domcontentloaded'
						);
					}

					// Ensure we have cart items
					const cartForm = customerPage.locator(
						'.woocommerce-cart-form, .wc-block-cart'
					);
					await expect( cartForm ).toBeVisible( { timeout: 10000 } );

					// Use helper, but explicitly pass the Blocks checkout URL
					await setupCheckout(
						customerPage,
						defaultBillingAddress,
						'/checkout'
					);
				} );

				test( 'using a basic card', { tag: '@critical' }, async () => {
					await fillCardDetails( customerPage, cards.basic );
					await placeOrder( customerPage );

					await expect(
						customerPage.getByRole( 'heading', {
							name: 'Order received',
						} )
					).toBeVisible();
				} );

				test( 'using a 3DS card', { tag: '@critical' }, async () => {
					await fillCardDetails( customerPage, cards[ '3ds' ] );
					await placeOrder( customerPage );
					await confirm3dsAuthentication( customerPage );

					await expect(
						customerPage.getByRole( 'heading', {
							name: 'Order received',
						} )
					).toBeVisible();
				} );
			} );
		}
	}
);
