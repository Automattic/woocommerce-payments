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

const RETRIGGER_AFTER_MS = 3000;
const BAIL_AFTER_MS = 6000;

const isWCPBCAjaxModeActive = () =>
	typeof wc_price_based_country_ajax_geo_params !== 'undefined';

const waitForWCPBCCurrency = ( upstream ) =>
	new Promise( ( resolve ) => {
		const $body = jQuery( document.body );
		const cleanups = [];
		const finish = ( value ) => {
			cleanups.forEach( ( fn ) => fn() );
			resolve( value );
		};

		const onEvent = ( _e, params ) => {
			if ( params?.code ) {
				finish( String( params.code ).toLowerCase() );
			}
		};
		$body.on( 'wc_price_based_country_set_currency_params', onEvent );
		cleanups.push( () =>
			$body.off( 'wc_price_based_country_set_currency_params', onEvent )
		);

		// WCPBC enqueues at priority 1 and fires its AJAX synchronously, so
		// we may attach after they've already started.
		// Re-trigger to force a second event we can catch.
		const retriggerTimer = setTimeout(
			() =>
				$body.triggerHandler(
					'wc_price_based_country_ajax_geolocation'
				),
			RETRIGGER_AFTER_MS
		);
		cleanups.push( () => clearTimeout( retriggerTimer ) );

		// Surrender rather than hang ECE forever.
		const bailTimer = setTimeout(
			async () => finish( await upstream ),
			BAIL_AFTER_MS
		);
		cleanups.push( () => clearTimeout( bailTimer ) );
	} );

addFilter(
	'wcpay.express-checkout.resolved-currency',
	'automattic/wcpay/express-checkout/wcpbc',
	( upstream ) =>
		isWCPBCAjaxModeActive() ? waitForWCPBCCurrency( upstream ) : upstream
);
