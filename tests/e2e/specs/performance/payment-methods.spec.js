/**
 * External dependencies
 */
import config from 'config';
const { merchant } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { setupProductCheckout } from '../../utils/payments';
import { shopperWCP, merchantWCP } from '../../utils';
import {
	recreatePerformanceFile,
	getLoadingDurations,
	logPerformanceResult,
} from '../../utils/performance';

// The total number of times we should run our tests for averaging.
const TOTAL_TRIALS = 3;

const averageMetrics = ( metrics, i ) => {
	const results = {};
	for ( const [ key, value ] of Object.entries( metrics ) ) {
		results[ key ] = value.reduce( ( prev, curr ) => prev + curr ) / i;
	}
	return results;
};

/**
 * This helper function goes to checkout page *i* times. Wait
 * for the given card selector to load, retrieve all the metrics
 * and find the average.
 *
 * @param {string} selector CSS selector.
 * @return {mixed} The averaged results.
 */
const measureCheckoutMetrics = async ( selector ) => {
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

	let i = TOTAL_TRIALS;
	while ( i-- ) {
		await page.reload();
		await page.waitForSelector( selector );
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
	return results;
};

describe( 'Checkout page performance', () => {
	beforeAll( async () => {
		// Start a new file for every run.
		recreatePerformanceFile();
	} );

	describe( 'Stripe element', () => {
		beforeEach( async () => {
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' )
			);
		} );

		afterEach( async () => {
			// Clear the cart at the end so it's ready for another test
			await shopperWCP.emptyCart();
		} );

		it( 'measures averaged page load metrics', async () => {
			const results = await measureCheckoutMetrics(
				'#wcpay-card-element iframe'
			);
			logPerformanceResult(
				'Stripe element: Average',
				averageMetrics( results, TOTAL_TRIALS )
			);
		} );
	} );

	describe( 'UPE', () => {
		beforeEach( async () => {
			// Activate UPE
			await merchant.login();
			await merchantWCP.activateUpe();
			await merchant.logout();

			// Setup cart
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' )
			);
		} );

		afterEach( async () => {
			// Clear the cart at the end so it's ready for another test
			await shopperWCP.emptyCart();

			// Deactivate UPE
			await merchant.login();
			await merchantWCP.deactivateUpe();
			await merchant.logout();
		} );

		it( 'measures averaged page load metrics', async () => {
			const results = await measureCheckoutMetrics(
				'#wcpay-upe-element iframe'
			);
			logPerformanceResult(
				'Stripe UPE: Average',
				averageMetrics( results, TOTAL_TRIALS )
			);
		} );
	} );

	describe( 'WooPay withou UPE', () => {
		beforeEach( async () => {
			// Activate UPE
			await merchant.login();
			await merchantWCP.activateWooPay();
			await merchant.logout();

			// Setup cart
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' )
			);
		} );

		afterEach( async () => {
			// Clear the cart at the end so it's ready for another test
			await shopperWCP.emptyCart();

			// Deactivate UPE
			await merchant.login();
			await merchantWCP.deactivateWooPay();
			await merchant.logout();
		} );

		it( 'measures averaged page load metrics', async () => {
			const results = await measureCheckoutMetrics(
				'#wcpay-card-element iframe'
			);
			logPerformanceResult(
				'WooPay: Average',
				averageMetrics( results, TOTAL_TRIALS )
			);
		} );
	} );
} );
