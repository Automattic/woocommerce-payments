/**
 * External dependencies
 */
import domReady from '@wordpress/dom-ready';

/**
 * Internal dependencies
 */
import { Event } from './event';
import { getConfig } from 'wcpay/utils/checkout';
import { getPaymentRequestData } from 'wcpay/payment-request/utils';

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
			hosting_provider: wcpaySettings.trackingInfo?.hosting_provider,
		} );

		// Filter our undefined properties.
		for ( const key in eventProperties ) {
			if ( eventProperties[ key ] === undefined ) {
				delete eventProperties[ key ];
			}
		}
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
 */
export const recordUserEvent = (
	eventName: string,
	eventProperties: Record< string, unknown > = {}
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
	fetch( ajaxUrl, {
		method: 'post',
		body,
	} );
};

/**
 * Retrieves the value of the 'tk_ai' cookie from the document's cookies.
 *
 * @return {string | undefined} The value of the 'tk_ai' cookie if found, otherwise undefined.
 */
const getIdentityCookieValue = (): string | undefined => {
	const nameEQ = 'tk_ai=';
	const ca = document.cookie.split( ';' ); // Split cookie string and get all individual name=value pairs in an array
	for ( let i = 0; i < ca.length; i++ ) {
		let c = ca[ i ];
		while ( c.charAt( 0 ) === ' ' ) c = c.substring( 1, c.length ); // Trim leading whitespace
		if ( c.indexOf( nameEQ ) === 0 )
			return c.substring( nameEQ.length, c.length ); // Check if it's the right cookie and return its value
	}
	return undefined; // Return undefined if the cookie is not found
};

/**
 * Asynchronously retrieves the user's Tracks identity, either from a cookie or via an Ajax request.
 *
 * @return {Promise<string | undefined>} A promise that resolves to a stringified object containing the user's Tracks identity,
 *                                        or undefined if the identity cannot be obtained.
 */
export const getTracksIdentity = async (): Promise< string | undefined > => {
	// if cookie is set, get identity from the cookie.
	// eslint-disable-next-line
	let _ui = getIdentityCookieValue();
	if ( _ui ) {
		const data = { _ut: 'anon', _ui: _ui };
		return JSON.stringify( data );
	}
	// Otherwise get it via an Ajax request.
	const nonce =
		getConfig( 'platformTrackerNonce' ) ??
		getPaymentRequestData( 'nonce' )?.platform_tracker;
	const ajaxUrl =
		getConfig( 'ajaxUrl' ) ?? getPaymentRequestData( 'ajax_url' );
	const body = new FormData();

	body.append( 'tracksNonce', nonce );
	body.append( 'action', 'get_identity' );
	try {
		const response = await fetch( ajaxUrl, {
			method: 'post',
			body,
		} );
		if ( ! response.ok ) {
			return undefined;
		}

		const data = await response.json();
		if ( data.success && data.data && data.data._ui && data.data._ut ) {
			return JSON.stringify( data.data );
		}
		return undefined;
	} catch ( error ) {
		return undefined;
	}
};
