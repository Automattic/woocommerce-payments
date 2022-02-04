/**
 * External dependencies
 */
import config from 'config';

const { shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */

import {
	fillCardDetails,
	setupProductCheckout,
	confirmCardAuthentication,
} from '../../../utils/payments';

import { shopperWCP } from '../../../utils';

describe( 'Successful purchase', () => {
	beforeEach( async () => {
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
	} );

	afterAll( async () => {
		// Clear the cart at the end so it's ready for another test
		await shopperWCP.emptyCart();
	} );

	it( 'using a basic card', async () => {
		const card = config.get( 'cards.basic' );
		await fillCardDetails( page, card );
		await shopper.placeOrder();
		await expect( page ).toMatch( 'Order received' );
	} );

	it( 'using a 3DS card', async () => {
		const card = config.get( 'cards.3ds' );
		await fillCardDetails( page, card );
		await expect( page ).toClick( '#place_order' );
		await confirmCardAuthentication( page, '3DS' );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
		await expect( page ).toMatch( 'Order received' );
	} );
} );
