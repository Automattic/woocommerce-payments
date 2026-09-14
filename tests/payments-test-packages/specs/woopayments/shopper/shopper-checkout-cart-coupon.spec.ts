/**
 * External dependencies
 */
import { test, expect, getAuthState } from '../../../fixtures/auth';
import type { BrowserContext, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import { goToCart, goToCheckout } from '../../../utils/shopper-navigation';
import {
	addToCartFromShopPage,
	emptyCart,
	fillBillingAddress,
	fillCardDetails,
	placeOrder,
	removeCoupon,
	setupCheckout,
} from '../../../utils/shopper';

const couponCode = 'free';

test.describe(
	'Checkout with free coupon & after modifying cart on Checkout page',
	{ tag: '@shopper' },
	() => {
		let shopperContext: BrowserContext;
		let shopperPage: Page;

		test.beforeAll( async ( { browser } ) => {
			shopperContext = await browser.newContext( {
				storageState: await getAuthState( browser, 'customer' ),
			} );
			shopperPage = await shopperContext.newPage();
		} );

		test.afterAll( async () => {
			await shopperContext?.close();
		} );

		test.beforeEach( async () => {
			await addToCartFromShopPage( shopperPage );
			await goToCart( shopperPage );
			await shopperPage
				.getByPlaceholder( 'Coupon code' )
				.fill( couponCode );
			await shopperPage
				.getByRole( 'button', { name: 'Apply coupon' } )
				.click();
			await expect(
				shopperPage.getByText( 'Coupon code applied successfully' )
			).toBeVisible();
		} );

		test.afterEach( async () => {
			await emptyCart( shopperPage );
		} );

		test( 'Checkout with a free coupon', async () => {
			await goToCheckout( shopperPage );
			await fillBillingAddress(
				shopperPage,
				config.addresses.customer.billing
			);
			await placeOrder( shopperPage );
			await shopperPage.waitForURL( /\/order-received\//, {
				waitUntil: 'load',
			} );
			await expect(
				shopperPage.getByRole( 'heading', {
					name: 'Order received',
				} )
			).toBeVisible();
		} );

		test( 'Remove free coupon, then checkout', async () => {
			await goToCheckout( shopperPage );
			await removeCoupon( shopperPage );
			await setupCheckout(
				shopperPage,
				config.addresses.customer.billing
			);
			await fillCardDetails( shopperPage, config.cards.basic );
			await placeOrder( shopperPage );
			await shopperPage.waitForURL( /\/order-received\//, {
				waitUntil: 'load',
			} );
			await expect(
				shopperPage.getByRole( 'heading', {
					name: 'Order received',
				} )
			).toBeVisible();
		} );
	}
);
