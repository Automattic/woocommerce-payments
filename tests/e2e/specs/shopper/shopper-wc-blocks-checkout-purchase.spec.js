/**
 * External dependencies
 */
import config from 'config';

const { shopper, merchant, uiUnblocked } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */

import { shopperWCP, merchantWCP } from '../../utils';

const billingDetails = config.get( 'addresses.customer.billing' );
const simpleProductName = config.get( 'products.simple.name' );

import {
	fillCardDetailsWCB,
	confirmCardAuthentication,
} from '../../utils/payments';

describe( 'WooCommerce Blocks > Successful purchase', () => {
	beforeAll( async () => {
		await merchant.login();
		await merchantWCP.addNewPageCheckoutWCB();
		await merchant.logout();
	} );

	afterAll( async () => {
		await shopper.logout();
	} );

	it( 'using a basic card', async () => {
		await shopper.login();
		await shopper.goToShop();
		await shopper.addToCartFromShopPage( simpleProductName );
		await shopper.goToCheckout();
		await uiUnblocked();
		await shopper.fillBillingDetails( billingDetails );
		await shopperWCP.openCheckoutWCB();

		// Fill the required first and last name fields
		await page.type( '#shipping-first_name', 'I am' );
		await page.type( '#shipping-last_name', 'Customer' );

		// Fill CC details and purchase the product
		const card = config.get( 'cards.basic' );
		await fillCardDetailsWCB( page, card );
		await expect( page ).toClick( 'button.components-button' );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
		await expect( page ).toMatch( 'Order received' );
	} );

	it( 'using a 3DS card', async () => {
		await shopper.goToShop();
		await shopper.addToCartFromShopPage( simpleProductName );
		await shopper.goToCheckout();
		await uiUnblocked();
		await shopperWCP.openCheckoutWCB();

		// Fill CC details and purchase the product
		const card = config.get( 'cards.3ds' );
		await fillCardDetailsWCB( page, card );
		await expect( page ).toClick( 'button.components-button' );
		await confirmCardAuthentication( page, '3DS' );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
		await expect( page ).toMatch( 'Order received' );
	} );
} );
