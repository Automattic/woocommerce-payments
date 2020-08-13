/**
 * External dependencies
 */
import config from 'config';

/**
 * Internal dependencies
 */
import { CustomerFlow } from '../utils';
import { fillCardDetails, setupProductCheckout, confirmCardAuthentication } from '../utils/payments';

describe( 'Successful purchase', () => {
	beforeAll( async () => {
		await page.goto( config.get( 'url' ), { waitUntil: 'networkidle0' } );
	} );

	it( 'using a basic card', async () => {
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		const card = config.get( 'cards.basic' );
		await fillCardDetails( page, card );
		await CustomerFlow.placeOrder();
		await expect( page ).toMatch( 'Order received' );
	} );

	it( 'using a 3DS card and account signup', async () => {
		await setupProductCheckout( {
			...config.get( 'addresses.customer.billing' ),
			...config.get( 'users.guest' ),
		} );
		await CustomerFlow.toggleCreateAccount();
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
