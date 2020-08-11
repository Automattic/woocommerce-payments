/**
 * External dependencies
 */
import config from 'config';

/**
 * Internal dependencies
 */
import { CustomerFlow } from '../utils';
import { fillCardDetails, setupProductCheckout } from '../utils/payments';

describe( 'Successful purchase', () => {
	beforeAll( async () => {
		await page.goto( config.get( 'url' ), { waitUntil: 'networkidle0' } );
	} );

	it( 'successful purchase', async () => {
		await setupProductCheckout();
		const card = config.get( 'cards.basic' );
		await fillCardDetails( page, card );
		await CustomerFlow.placeOrder();
		await expect( page ).toMatch( 'Order received' );
	} );
} );
