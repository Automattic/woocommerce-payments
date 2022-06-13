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

const averageMetrics = ( metrics, i ) => {
	const results = {};
	for ( const [ key, value ] of Object.entries( metrics ) ) {
		results[ key ] = value.reduce( ( prev, curr ) => prev + curr ) / i;
	}
	return results;
};

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
		const totalTrials = 3;
		await expect( page ).toMatch( 'Checkout' );

		// Run performance tests a few times, then take the average.
		const results = {
			serverResponse: [],
			firstPaint: [],
			domContentLoaded: [],
			loaded: [],
			firstContentfulPaint: [],
			firstBlock: [],
		};

		let i = totalTrials;
		while ( i-- ) {
			await page.reload();
			await page.waitForSelector(
				'#payment_method_woocommerce_payments'
			);
			const {
				serverResponse,
				firstPaint,
				domContentLoaded,
				loaded,
				firstContentfulPaint,
				firstBlock,
			} = await getLoadingDurations();

			results.serverResponse.push( serverResponse );
			results.firstPaint.push( firstPaint );
			results.domContentLoaded.push( domContentLoaded );
			results.loaded.push( loaded );
			results.firstContentfulPaint.push( firstContentfulPaint );
			results.firstBlock.push( firstBlock );
		}
		console.log( 'All the trial results', results );
		console.log( 'Average', averageMetrics( results, totalTrials ) );
	} );
} );
