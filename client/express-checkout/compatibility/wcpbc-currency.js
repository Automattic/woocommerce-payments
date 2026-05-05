/* global jQuery, wc_price_based_country_ajax_geo_params */

/**
 * "Price Based on Country for WooCommerce" in AJAX-geolocation mode resolves
 * the visitor's currency client-side after page render, so the value
 * localized into `wcpayExpressCheckoutParams.checkout.currency_code` is the
 * store base.
 * We listen for the event WCPBC fires once it has resolved, with a watchdog
 * in case it doesn't.
 */

import { addFilter } from '@wordpress/hooks';

const SOFT_TIMEOUT_MS = 3000;
const HARD_TIMEOUT_MS = 6000;

const isWCPBCAjaxModeActive = () =>
	typeof wc_price_based_country_ajax_geo_params !== 'undefined';

addFilter(
	'wcpay.express-checkout.resolved-currency',
	'automattic/wcpay/express-checkout/wcpbc',
	( upstream ) => {
		if ( ! isWCPBCAjaxModeActive() ) {
			return upstream;
		}

		return new Promise( ( resolve ) => {
			const $body = jQuery( document.body );

			const handler = ( _event, params ) => {
				const code = params?.code;
				if ( ! code ) {
					return;
				}
				cleanup();
				resolve( String( code ).toLowerCase() );
			};

			const cleanup = () => {
				$body.off(
					'wc_price_based_country_set_currency_params',
					handler
				);
				clearTimeout( softTimer );
				clearTimeout( hardTimer );
			};

			$body.on( 'wc_price_based_country_set_currency_params', handler );

			// WCPBC enqueues at priority 1 and fires its AJAX synchronously,
			// so we may attach after they've already started.
			// Re-trigger to force a second event we can catch.
			const softTimer = setTimeout( () => {
				$body.triggerHandler(
					'wc_price_based_country_ajax_geolocation'
				);
			}, SOFT_TIMEOUT_MS );

			// Surrender rather than hang ECE forever.
			const hardTimer = setTimeout( async () => {
				cleanup();
				resolve( await upstream );
			}, HARD_TIMEOUT_MS );
		} );
	}
);
