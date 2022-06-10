/**
 * External dependencies
 */
import config from 'config';

/**
 * Internal dependencies
 */
import { setupProductCheckout } from '../../utils/payments';
import { shopperWCP } from '../../utils';
import { getLoadingDurations } from '../../utils/performance';

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
		await page.waitForSelector( '#payment_method_woocommerce_payments' );
		const {
			serverResponse,
			firstPaint,
			domContentLoaded,
			loaded,
			firstContentfulPaint,
			firstBlock,
		} = await getLoadingDurations();

		await expect( page ).toMatch( 'Checkout' );
		console.log(
			'result',
			serverResponse,
			firstPaint,
			domContentLoaded,
			loaded,
			firstContentfulPaint,
			firstBlock
		);
	} );
} );
