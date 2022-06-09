/**
 * External dependencies
 */
import config from 'config';

/**
 * Internal dependencies
 */
import { setupProductCheckout } from '../../utils/payments';
import { shopperWCP } from '../../utils';

describe( 'Checkout page performance', () => {
	beforeEach( async () => {
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
	} );

	afterAll( async () => {
		// Clear the cart at the end so it's ready for another test
		await shopperWCP.emptyCart();
	} );

	it( 'measures on page load', async () => {
		await expect( page ).toMatch( 'Checkout' );
	} );
} );
