/**
 * External dependencies
 */
import { appendFileSync, existsSync, mkdirSync, truncateSync } from 'fs';

/**
 * Internal dependencies
 */
import {
	PERFORMANCE_REPORT_DIR,
	PERFORMANCE_REPORT_FILENAME,
	NUMBER_OF_TRIALS,
} from './constants';

async function getLoadingDurations() {
	return await page.evaluate( () => {
		const {
			requestStart,
			responseStart,
			responseEnd,
			domContentLoadedEventEnd,
			loadEventEnd,
		} = performance.getEntriesByType( 'navigation' )[ 0 ];
		const paintTimings = performance.getEntriesByType( 'paint' );

		let firstPaintTimings, firstContentfulPaintTimings;

		paintTimings.forEach( ( item ) => {
			if ( 'first-paint' === item.name ) {
				firstPaintTimings = item;
			}
			if ( 'first-contentful-paint' === item.name ) {
				firstContentfulPaintTimings = item;
			}
		} );

		// Returns metrics in milliseconds (10^-3). Spec uses DOMHighResTimeStamp https://www.w3.org/TR/hr-time-2/#sec-domhighrestimestamp.
		return {
			// Server side metric.
			serverResponse: responseStart - requestStart,
			// For client side metrics, consider the end of the response (the
			// browser receives the HTML) as the start time (0).
			firstPaint: firstPaintTimings.startTime - responseEnd,
			domContentLoaded: domContentLoadedEventEnd - responseEnd,
			loaded: loadEventEnd - responseEnd,
			firstContentfulPaint:
				firstContentfulPaintTimings.startTime - responseEnd,
			// This is evaluated right after Puppeteer found the block selector.
			firstBlock: performance.now() - responseEnd,
		};
	} );
}

/**
 * Writes a line to the e2e performance result.
 *
 * @param {string} description A title that describe this metric
 * @param {Object} metrics array of metrics to record.
 */
export const logPerformanceResult = ( description, metrics ) => {
	appendFileSync(
		PERFORMANCE_REPORT_FILENAME,
		JSON.stringify( { description, ...metrics } ) + '\n'
	);
};

/**
 * Wipe the existing performance file. Also make sure the "report" folder exists.
 */
export const recreatePerformanceFile = () => {
	if ( ! existsSync( PERFORMANCE_REPORT_DIR ) ) {
		mkdirSync( PERFORMANCE_REPORT_DIR );
	}

	if ( existsSync( PERFORMANCE_REPORT_FILENAME ) ) {
		truncateSync( PERFORMANCE_REPORT_FILENAME );
	}
};

/**
 * Takes the metric object and for each of the property, reduce to the average.
 *
 * @param {Object} metrics An object containing multiple trials' data.
 * @return {Object} The averaged results.
 */
export const averageMetrics = ( metrics ) => {
	const results = {};
	for ( const [ key, value ] of Object.entries( metrics ) ) {
		results[ key ] =
			value.reduce( ( prev, curr ) => prev + curr ) / NUMBER_OF_TRIALS;
	}
	return results;
};

/**
 * This helper function goes to checkout page *i* times. Wait
 * for the given card selector to load, retrieve all the metrics
 * and find the average.
 *
 * @param {string} selector CSS selector.
 * @param {number} numberOfTrials The number of trials we would like to do.
 * @return {Object} The averaged results.
 */
export const measureCheckoutMetrics = async ( selector ) => {
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

	let i = NUMBER_OF_TRIALS;
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
