/**
 * External dependencies
 */
import config from 'config';

const {
	shopper,
	merchant,
	createSimpleProduct,
} = require( '@woocommerce/e2e-utils' );

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
const productName = 'Shirt';

import {
	fillCardDetailsWCB,
	confirmCardAuthentication,
} from '../../utils/payments';

describeif( RUN_WC_BLOCKS_TESTS )(
	'WooCommerce Blocks > Successful purchase',
	() => {
		beforeAll( async () => {
			await merchant.login();
			await createSimpleProduct( productName );
			await merchantWCP.addNewPageCheckoutWCB();
			await merchant.logout();
		} );

		afterAll( async () => {
			await shopper.logout();
		} );

		it( 'using a basic card', async () => {
			await shopper.login();
			await shopper.goToShop();
			await shopper.addToCartFromShopPage( productName );
			await shopperWCP.openCheckoutWCB();
			await shopperWCP.fillBillingDetailsWCB( billingDetails );

			// Fill CC details and purchase the product
			const card = config.get( 'cards.basic' );
			await fillCardDetailsWCB( page, card );
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
