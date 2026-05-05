/* global wcpayAsyncPriceConfig */

/**
 * In cache-optimized multi-currency mode, the async price renderer resolves
 * the visitor's currency client-side and publishes it on
 * `window.wcpayAsyncCurrency.ready`. We await it.
 */

import { addFilter } from '@wordpress/hooks';

const BAIL_AFTER_MS = 6000;

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
			setTimeout( async () => resolve( await upstream ), BAIL_AFTER_MS );
		} );

		return Promise.race( [
			ready.then( ( code ) =>
				code ? String( code ).toLowerCase() : upstream
			),
			fallback,
		] );
	}
);
