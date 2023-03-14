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

describe( 'Checkout with free Cart & after Cart updates on Checkout page', () => {
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
		await clearAndFillInput( '#coupon_code', 'free' );
		await expect( page ).toClick( 'button[name="apply_coupon"]' );
	} );

	afterAll( async () => {
		// Clear the cart at the end so it's ready for another test
		await shopperWCP.emptyCart();
	} );

	it( 'checkout a free cart', async () => {
		await shopper.goToCheckout();
		await shopper.fillBillingDetails( billingDetails );
		await page.waitFor( 1000 );
		await uiUnblocked();
		await shopper.placeOrder();
		await expect( page ).toMatch( 'Order received' );
	} );

	it( 'update a free cart via AJAX and checkout with WCPay', async () => {
		await shopper.goToCheckout();
		await page.click( '.woocommerce-remove-coupon' );
		await uiUnblocked();
		await setupCheckout( billingDetails );
		await fillCardDetails( page, card );
		await shopper.placeOrder();
		await expect( page ).toMatch( 'Order received' );
	} );

	it( 'update a free cart via AJAX and checkout with WCPay Blocks', async () => {
		await shopperWCP.openCheckoutWCB();
		await shopperWCP.fillBillingDetailsWCB( billingDetails );
		await fillCardDetailsWCB( page, card );
		await page.waitForSelector(
			'.wc-block-components-main button:not(:disabled)'
		);
		await expect( page ).toClick( 'button', { text: 'Place Order' } );
		await page.waitForSelector( 'div.woocommerce-order' );
		await expect( page ).toMatch( 'p', {
			text: 'Thank you. Your order has been received.',
		} );
	} );
} );

describe( 'Testing putting a second test in same file', () => {
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
		await clearAndFillInput( '#coupon_code', 'free' );
		await expect( page ).toClick( 'button[name="apply_coupon"]' );
	} );

	it( 'update a free cart via AJAX and checkout with UPE', async () => {
		await shopper.goToCheckout();
		await page.click( '.woocommerce-remove-coupon' );
		await uiUnblocked();
		await setupCheckout( billingDetails );
		await fillCardDetails( page, card );
		await shopper.placeOrder();
		await expect( page ).toMatch( 'Order not received' );
	} );
} );
