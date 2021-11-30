/**
 * Important note:
 * This test won't be successful in -dev (non-headless) mode, because different selectors are present in page editor.
 * Speaking of addNewPageCheckoutWCB() method when creating a new Checkout page with WCB block inside (37th line).
 * Might be Chromium browser's behavior, but good to mention that the correct selectors are present in headless mode,
 * so testing headless would be what the user really see in Chrome, Firefox, Opera...
 *
 * External dependencies
 */
import config from 'config';

const { shopper, merchant } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import {
	shopperWCP,
	merchantWCP,
	describeif,
	RUN_WC_BLOCKS_TESTS,
} from '../../utils';

const billingDetails = config.get( 'addresses.customer.billing' );
const productName = config.get( 'products.simple.name' );

import {
	fillCardDetailsWCB,
	confirmCardAuthentication,
} from '../../utils/payments';

describeif( RUN_WC_BLOCKS_TESTS )(
	'WooCommerce Blocks > Successful purchase',
	() => {
		beforeAll( async () => {
			await merchant.login();
			await merchantWCP.addNewPageCheckoutWCB();
			await merchant.logout();
		} );

		it( 'using a basic card', async () => {
			await shopper.goToShop();
			await shopper.addToCartFromShopPage( productName );
			await shopperWCP.openCheckoutWCB();
			await shopperWCP.fillBillingDetailsWCB( billingDetails );

			// Fill CC details and purchase the product
			const card = config.get( 'cards.basic' );
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

		it( 'using a 3DS card', async () => {
			await shopper.goToShop();
			await shopper.addToCartFromShopPage( productName );
			await shopperWCP.openCheckoutWCB();
			await shopperWCP.fillBillingDetailsWCB( billingDetails );

			// Fill CC details and purchase the product
			const card = config.get( 'cards.3ds' );
			await fillCardDetailsWCB( page, card );
			await page.waitForSelector(
				'.wc-block-components-main button:not(:disabled)'
			);
			await expect( page ).toClick( 'button', { text: 'Place Order' } );
			await confirmCardAuthentication( page, '3DS' );
			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );
			await page.waitForSelector( 'div.woocommerce-order' );
			await expect( page ).toMatch( 'p', {
				text: 'Thank you. Your order has been received.',
			} );
		} );
	}
);
