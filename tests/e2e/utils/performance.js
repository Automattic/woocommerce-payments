export async function getLoadingDurations() {
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
