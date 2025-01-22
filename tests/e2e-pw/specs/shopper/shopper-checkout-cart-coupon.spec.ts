/**
 * External dependencies
 */
import test, { expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../config/default';
import {
	goToCart,
	goToCheckout,
	goToShop,
} from '../../utils/shopper-navigation';
import { useShopper } from '../../utils/helpers';
import {
	addToCartFromShopPage,
	emptyCart,
	fillBillingAddress,
	fillCardDetails,
	placeOrder,
	setupCheckout,
} from '../../utils/shopper';

const productName = config.products.simple.name;

test.describe(
	'Checkout with free coupon & after modifying cart on Checkout page',
	() => {
		// All tests will use the shopper only.
		useShopper();

		test.beforeEach( async ( { page } ) => {
			await goToShop( page );
			await addToCartFromShopPage( page, productName );
			await goToCart( page );
			await page.getByPlaceholder( 'Coupon code' ).fill( 'free' );
			await page.getByRole( 'button', { name: 'Apply coupon' } ).click();
		} );

		test.afterEach( async ( { page } ) => {
			await emptyCart( page );
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
			await page.getByRole( 'link', { name: '[Remove]' } ).click();
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
