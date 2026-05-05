/* global wcpayAsyncPriceConfig */

/**
 * Resolves the customer's currency for ECE when WooPayments multi-currency
 * is running in cache-optimized mode without a session.
 *
 * In that mode the server intentionally avoids creating a session and ships
 * skeleton prices, so `get_woocommerce_currency()` at button-handler enqueue
 * time returns the store default. The async price renderer fetches the real
 * `selected_currency` from `/wc/v3/payments/multi-currency/public/config`
 * and publishes a `window.wcpayAsyncCurrency.ready` promise; we await it.
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
