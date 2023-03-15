/**
 * External dependencies
 */
import config from 'config';

const {
	shopper,
	merchant,
	clearAndFillInput,
	uiUnblocked,
} = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import {
	setupCheckout,
	fillCardDetails,
	fillCardDetailsWCB,
} from '../../../utils/payments';
import { shopperWCP, merchantWCP, checkPageExists } from '../../../utils';

const productName = config.get( 'products.simple.name' );
const billingDetails = config.get( 'addresses.customer.billing' );
const card = config.get( 'cards.basic' );
const couponInputSelector = '#coupon_code';
const applyCouponSelector = 'button[name="apply_coupon"]';
const removeCouponSelector = '.woocommerce-remove-coupon';
const blocksPageEnabledSelector =
	'.wc-block-components-main button:not(:disabled)';

describe( 'Checkout with free coupon & after modifying cart on Checkout page', () => {
	describe( 'Classic Checkout', () => {
		beforeAll( async () => {
			try {
				await checkPageExists( 'checkout-wcb' );
			} catch ( error ) {
				await merchant.login();
				await merchantWCP.addNewPageCheckoutWCB();
				await merchant.logout();
			}
		} );

		beforeEach( async () => {
			await shopper.goToShop();
			await shopper.addToCartFromShopPage( productName );
			await shopper.goToCart();
			await clearAndFillInput( couponInputSelector, 'free' );
			await expect( page ).toClick( applyCouponSelector );
		} );

		afterAll( async () => {
			// Clear the cart at the end so it's ready for another test
			await shopperWCP.emptyCart();
		} );

		it( 'Checkout with a free coupon', async () => {
			await shopper.goToCheckout();
			await shopper.fillBillingDetails( billingDetails );
			await page.waitFor( 1000 );
			await uiUnblocked();
			await shopper.placeOrder();
			await expect( page ).toMatch( 'Order received' );
		} );

		it( 'Remove free coupon, then checkout with Classic Checkout', async () => {
			await shopper.goToCheckout();
			await page.click( removeCouponSelector );
			await uiUnblocked();
			await setupCheckout( billingDetails );
			await fillCardDetails( page, card );
			await shopper.placeOrder();
			await expect( page ).toMatch( 'Order received' );
		} );

		it( 'Remove free coupon, then checkout with WooCommerce Blocks', async () => {
			await shopperWCP.openCheckoutWCB();
			await shopperWCP.fillBillingDetailsWCB( billingDetails );
			await fillCardDetailsWCB( page, card );
			await page.waitForSelector( blocksPageEnabledSelector );
			await expect( page ).toClick( 'button', { text: 'Place Order' } );
			await page.waitForSelector( 'div.woocommerce-order' );
			await expect( page ).toMatch( 'p', {
				text: 'Thank you. Your order has been received.',
			} );
		} );
	} );

	describe( 'UPE', () => {
		beforeAll( async () => {
			await merchant.login();
			await merchantWCP.activateSplitUpe();
			await merchant.logout();
		} );

		afterAll( async () => {
			await merchant.login();
			await merchantWCP.deactivateSplitUpe();
			await merchant.logout();
		} );

		beforeEach( async () => {
			await shopper.goToShop();
			await shopper.addToCartFromShopPage( productName );
			await shopper.goToCart();
			await clearAndFillInput( couponInputSelector, 'free' );
			await expect( page ).toClick( applyCouponSelector );
		} );

		it( 'Remove free coupon, then checkout with UPE', async () => {
			await shopper.goToCheckout();
			await page.click( removeCouponSelector );
			await uiUnblocked();
			await setupCheckout( billingDetails );
			await fillCardDetails( page, card );
			await shopper.placeOrder();
			await expect( page ).toMatch( 'Order received' );
		} );
	} );
} );
