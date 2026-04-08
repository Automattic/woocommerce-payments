/**
 * External dependencies
 */
import { appendFileSync, existsSync, mkdirSync, truncateSync } from 'fs';
import { expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	performanceReportDir,
	performanceReportfilename,
	performanceNumberOfTrials,
} from './constants';

type Metrics = Record< string, number[] >;
type AverageMetrics = Record< string, number >;

async function getLoadingDurations( page: Page ) {
	return await page.evaluate( () => {
		const navigation = performance.getEntriesByType(
			'navigation'
		)[ 0 ] as PerformanceNavigationTiming;

		const {
			requestStart,
			responseStart,
			responseEnd,
			domContentLoadedEventEnd,
			loadEventEnd,
		} = navigation;
		const paintTimings = performance.getEntriesByType( 'paint' );

		let firstPaintTimings: PerformanceEntry,
			firstContentfulPaintTimings: PerformanceEntry;

		paintTimings.forEach( ( item ) => {
			if ( item.name === 'first-paint' ) {
				firstPaintTimings = item;
			}
			if ( item.name === 'first-contentful-paint' ) {
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
			// This is evaluated right after we find the block selector.
			firstBlock: performance.now() - responseEnd,
		};
	} );
}

/**
 * Writes a line to the e2e performance result.
 */
export const logPerformanceResult = (
	description: string,
	metrics: AverageMetrics
) => {
	appendFileSync(
		performanceReportfilename,
		JSON.stringify( { description, ...metrics } ) + '\n'
	);
};

/**
 * Wipe the existing performance file. Also make sure the "report" folder exists.
 */
export const recreatePerformanceFile = () => {
	if ( ! existsSync( performanceReportDir ) ) {
		mkdirSync( performanceReportDir );
	}

	if ( existsSync( performanceReportfilename ) ) {
		truncateSync( performanceReportfilename );
	}
};

/**
 * Takes the metric object and for each of the property, reduce to the average.
 */
export const averageMetrics = ( metrics: Metrics ): AverageMetrics => {
	const results = {};
	for ( const [ key, value ] of Object.entries( metrics ) ) {
		results[ key ] =
			value.reduce( ( prev, curr ) => prev + curr ) /
			performanceNumberOfTrials;
	}
	return results;
};

/**
 * This helper function goes to checkout page *i* times. Wait
 * for the given card selector to load, retrieve all the metrics
 * and find the average.
 */
export const measureCheckoutMetrics = async (
	page: Page,
	selector: string
): Promise< Metrics > => {
	await expect( page.getByText( 'Checkout' ).first() ).toBeVisible();

	// Run performance tests a few times, then take the average.
	const results = {
		serverResponse: [],
		firstPaint: [],
		domContentLoaded: [],
		loaded: [],
		firstContentfulPaint: [],
		firstBlock: [],
	};

	let i = performanceNumberOfTrials;
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
		} = await getLoadingDurations( page );

		results.serverResponse.push( serverResponse );
		results.firstPaint.push( firstPaint );
		results.domContentLoaded.push( domContentLoaded );
		results.loaded.push( loaded );
		results.firstContentfulPaint.push( firstContentfulPaint );
		results.firstBlock.push( firstBlock );
	}
	return results;
};
