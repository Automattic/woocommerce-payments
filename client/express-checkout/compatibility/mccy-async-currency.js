/* global wcpayAsyncPriceConfig */

/**
 * Cache-optimized multi-currency mode resolves the visitor's currency
 * client-side via the async price renderer.
 * The renderer publishes the answer on `window.wcpayAsyncCurrency.ready`;
 * we await it.
 */

import { addFilter } from '@wordpress/hooks';

const HARD_TIMEOUT_MS = 6000;

const isCacheOptimizedAsyncModeActive = () =>
	typeof wcpayAsyncPriceConfig !== 'undefined';

addFilter(
	'wcpay.express-checkout.resolved-currency',
	'automattic/wcpay/express-checkout/mccy-async',
	( upstream ) => {
		if ( ! isCacheOptimizedAsyncModeActive() ) {
			return upstream;
		}

		const ready = window.wcpayAsyncCurrency?.ready;
		if ( ! ready || typeof ready.then !== 'function' ) {
			return upstream;
		}

		const fallback = new Promise( ( resolve ) => {
			setTimeout(
				async () => resolve( await upstream ),
				HARD_TIMEOUT_MS
			);
		} );

		return Promise.race( [
			ready.then( ( code ) =>
				code ? String( code ).toLowerCase() : upstream
			),
			fallback,
		] );
	}
);
