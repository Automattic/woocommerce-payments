/**
 * External dependencies
 */
import domReady from '@wordpress/dom-ready';
import { getConfig } from 'wcpay/utils/checkout';
import { getPaymentRequestData } from 'wcpay/payment-request/utils';

/**
 * Internal dependencies
 */
import { Event } from './event';

/**
 * Checks if site tracking is enabled.
 *
 * @return {boolean} True if site tracking is enabled.
 */
export const isEnabled = (): boolean => wcTracks.isEnabled;

/**
 * Records site event.
 *
 * By default Woo adds `url`, `blog_lang`, `blog_id`, `store_id`, `products_count`, and `wc_version`
 * properties to every event.
 *
 * @param {Event}  eventName         Name of the event.
 * @param {Object} [eventProperties] Event properties (optional).
 */
export const recordEvent = (
	eventName: Event,
	eventProperties: Record< string, unknown > = {}
): void => {
	// TODO: Load these properties in a new script to ensure it's available everywhere.
	// wcpaySettings is not available outside of WCPay pages.
	if ( window.wcpaySettings ) {
		// Add default properties to every event.
		Object.assign( eventProperties, {
			is_test_mode: wcpaySettings.testMode,
			jetpack_connected: wcpaySettings.isJetpackConnected,
			wcpay_version: wcpaySettings.version,
			woo_country_code: wcpaySettings.connect.country,
		} );
	}
	// Wc-admin track script is enqueued after ours, wrap in domReady
	// to make sure we're not too early.
	domReady( () => {
		const recordFunction = wc?.tracks?.recordEvent ?? wcTracks.recordEvent;
		recordFunction( eventName, eventProperties );
	} );
};

/**
 * Records events from buyers (aka shoppers).
 *
 * @param {string}  eventName         Name of the event.
 * @param {Object}  [eventProperties] Event properties (optional).
 * @param {boolean} isLegacy Event properties (optional).
 */
export const recordUserEvent = (
	eventName: string,
	eventProperties: Record< string, unknown > = {},
	isLegacy = false
): void => {
	const nonce =
		getConfig( 'platformTrackerNonce' ) ??
		getPaymentRequestData( 'nonce' )?.platform_tracker;
	const ajaxUrl =
		getConfig( 'ajaxUrl' ) ?? getPaymentRequestData( 'ajax_url' );
	const body = new FormData();

	body.append( 'tracksNonce', nonce );
	body.append( 'action', 'platform_tracks' );
	body.append( 'tracksEventName', eventName );
	body.append( 'tracksEventProp', JSON.stringify( eventProperties ) );
	body.append( 'isLegacy', JSON.stringify( isLegacy ) ); // formData does not allow appending booleans, so we stringify it - it is parsed back to a boolean on the PHP side.
	fetch( ajaxUrl, {
		method: 'post',
		body,
	} );
};
