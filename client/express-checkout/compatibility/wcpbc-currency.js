/* global jQuery, wc_price_based_country_ajax_geo_params */

/**
 * Resolves the customer's currency for ECE when "Price Based on Country for
 * WooCommerce" runs in AJAX-geolocation mode.
 *
 * The plugin defers country resolution to a client-side AJAX call that fires
 * after page render. Until that AJAX response lands, `get_woocommerce_currency()`
 * server-side returns the store base currency, which is what the ECE button
 * handler localizes into `wcpayExpressCheckoutParams.checkout.currency_code`.
 * If we hand that value to `stripe.elements({ currency })`, Stripe encodes it
 * into the confirmation_token and rejects the resulting PaymentIntent because
 * the cart's resolved currency disagrees.
 *
 * WCPBC fires `wc_price_based_country_set_currency_params` on the document
 * body once its AJAX has resolved (also fired by manual country switching).
 * We listen, watchdog-retrigger if a window passes silently, and surrender
 * to the upstream fallback if WCPBC never reports.
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

			// AJAX may have raced ahead of our listener (their script enqueues
			// at priority 1 and runs synchronously). Re-trigger to force a
			// second event we can catch.
			const softTimer = setTimeout( () => {
				$body.triggerHandler(
					'wc_price_based_country_ajax_geolocation'
				);
			}, SOFT_TIMEOUT_MS );

			// Surrender to the upstream value rather than block ECE
			// indefinitely if WCPBC never reports.
			const hardTimer = setTimeout( async () => {
				cleanup();
				resolve( await upstream );
			}, HARD_TIMEOUT_MS );
		} );
	}
);
