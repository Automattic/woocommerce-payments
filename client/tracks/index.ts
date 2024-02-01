/**
 * External dependencies
 */
import domReady from '@wordpress/dom-ready';
import { getConfig } from 'wcpay/utils/checkout';
import { getPaymentRequestData } from 'wcpay/payment-request/utils';

/**
 * Internal dependencies
 */
import events from './events';

declare const window: {
	wc: {
		tracks: {
			recordEvent: (
				eventName: string,
				eventProperties: Record< string, unknown >
			) => void;
		};
	};
	wcTracks: {
		isEnabled: boolean;
		recordEvent: (
			eventName: string,
			eventProperties: Record< string, unknown >
		) => void;
	};
	wcpaySettings: {
		testMode: boolean;
	};
} & Window;

/**
 * Checks if site tracking is enabled.
 *
 * @return {boolean} True if site tracking is enabled.
 */
export const isEnabled = (): boolean => window.wcTracks.isEnabled;

/**
 * Records site event.
 *
 * @param {string}  eventName        Name of the event.
 * @param {Object} [eventProperties] Event properties (optional).
 */
export const recordEvent = (
	eventName: string,
	eventProperties: Record< string, unknown > = {}
): void => {
	// Add `is_test_mode` property to every event.
	eventProperties.is_test_mode = window.wcpaySettings?.testMode;

	// Wc-admin track script is enqueued after ours, wrap in domReady
	// to make sure we're not too early.
	domReady( () => {
		const recordFunction =
			window.wc?.tracks?.recordEvent ?? window.wcTracks.recordEvent;
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

export { events };
