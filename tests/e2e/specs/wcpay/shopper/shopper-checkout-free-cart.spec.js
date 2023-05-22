/**
 * External dependencies
 */
import config from 'config';
/**
 * Internal dependencies
 */
import { fillCardDetails, setupCheckout } from '../../../utils/payments';
import { merchantWCP, shopperWCP } from '../../../utils';

const {
	shopper,
	merchant,
	clearAndFillInput,
	uiUnblocked,
} = require( '@woocommerce/e2e-utils' );

const productName = config.get( 'products.simple.name' );
const billingDetails = config.get( 'addresses.customer.billing' );
const card = config.get( 'cards.basic' );
const couponInputSelector = '#coupon_code';
const applyCouponSelector = 'button[name="apply_coupon"]';
const removeCouponSelector = '.woocommerce-remove-coupon';

describe( 'Checkout with free coupon & after modifying cart on Checkout page', () => {
	describe( 'Classic Checkout', () => {
		beforeEach( async () => {
			await shopper.goToShop();
			await shopper.addToCartFromShopPage( productName );
			await shopper.goToCart();
			await clearAndFillInput( couponInputSelector, 'free' );
			await expect( page ).toClick( applyCouponSelector );
			await uiUnblocked();
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

		afterAll( async () => {
			// Clear the cart at the end so it's ready for another test
			await shopperWCP.emptyCart();
		} );
	} );

	describe( 'UPE', () => {
		beforeAll( async () => {
			await merchant.login();
			await merchantWCP.activateSplitUpe();
			await merchant.logout();
		} );

		beforeEach( async () => {
			await shopper.goToShop();
			await shopper.addToCartFromShopPage( productName );
			await shopper.goToCart();
			await clearAndFillInput( couponInputSelector, 'free' );
			await expect( page ).toClick( applyCouponSelector );
			await uiUnblocked();
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

		afterAll( async () => {
			await merchant.login();
			await merchantWCP.deactivateSplitUpe();
			await merchant.logout();
		} );
	} );
} );
