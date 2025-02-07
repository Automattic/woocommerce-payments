/**
 * External dependencies
 */
import test, { expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import { goToCart, goToCheckout } from '../../../utils/shopper-navigation';
import { useShopper } from '../../../utils/helpers';
import {
	addToCartFromShopPage,
	emptyCart,
	fillBillingAddress,
	fillCardDetails,
	placeOrder,
	removeCoupon,
	setupCheckout,
} from '../../../utils/shopper';

test.describe(
	'Checkout with free coupon & after modifying cart on Checkout page',
	() => {
		// All tests will use the shopper only.
		useShopper();

		test.beforeEach( async ( { page } ) => {
			await addToCartFromShopPage( page );
			await goToCart( page );
			await page.getByPlaceholder( 'Coupon code' ).fill( 'free' );
			await page.getByRole( 'button', { name: 'Apply coupon' } ).click();
			await expect(
				page.getByText( 'Coupon code applied successfully' )
			).toBeVisible();
		} );

		test.afterEach( async ( { page } ) => {
			await emptyCart( page );
		} );

		/**
		 * This afterAll step is to ensure that there is no coupon stored in the customer session.
		 * This is done in cases where a test may be interrupted causing the coupon state to persist
		 * in the next Atomic workflow run.
		 */
		test.afterAll( async ( { browser } ) => {
			const cleanupPage = await browser.newPage();
			await addToCartFromShopPage( cleanupPage );
			await goToCart( cleanupPage );
			await removeCoupon( cleanupPage );
			await emptyCart( cleanupPage );
		} );

		test( 'Checkout with a free coupon', async ( { page } ) => {
			await goToCheckout( page );
			await fillBillingAddress( page, config.addresses.customer.billing );
			await placeOrder( page );
			await page.waitForURL( /\/order-received\//, {
				waitUntil: 'load',
			} );
			await expect(
				page.getByRole( 'heading', {
					name: 'Order received',
				} )
			).toBeVisible();
		} );

		test( 'Remove free coupon, then checkout', async ( { page } ) => {
			await goToCheckout( page );
			await removeCoupon( page );
			await setupCheckout( page, config.addresses.customer.billing );
			await fillCardDetails( page, config.cards.basic );
			await placeOrder( page );
			await page.waitForURL( /\/order-received\//, {
				waitUntil: 'load',
			} );
			await expect(
				page.getByRole( 'heading', {
					name: 'Order received',
				} )
			).toBeVisible();
		} );
	}
);
