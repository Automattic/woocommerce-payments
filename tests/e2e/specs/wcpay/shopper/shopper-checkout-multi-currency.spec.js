/**
 * External dependencies
 */
import config from 'config';

const { shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */

import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';
import { shopperWCP } from '../../../utils';

describe( 'Shopper Multi-Currency checkout', () => {
	beforeAll( async () => {
		await shopper.login();
	} );

	afterAll( async () => {
		// Clear the cart at the end so it's ready for another test
		await shopperWCP.emptyCart();
	} );

	it( 'should be able to checkout with different currency than the store currency', async () => {
		// Check that the order was placed successfully
		// Check that the order has the correct currency
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' ),
			[ [ config.get( 'products.simple.name' ), 1 ] ],
			'EUR'
		);
		const card = config.get( 'cards.basic' );
		await fillCardDetails( page, card );
		await shopper.placeOrder();
		await expect( page ).toMatch( 'Order received' );
		// should show correct currency in order received page
		expect(
			await page.$eval(
				'.woocommerce-order-overview__total',
				( el ) => el.textContent
			)
		).toMatch( /EUR/ );
	} );

	it( 'should show correct currency in order received page', async () => {} );

	it( 'should show the correct currency in the order page', async () => {} );

	it( 'should show the correct currency in a previous order with a different currency', async () => {} );
} );
