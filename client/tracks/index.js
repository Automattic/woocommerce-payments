/**
 * External dependencies
 */
import domReady from '@wordpress/dom-ready';

/**
 * Checks if site tracking is enabled.
 *
 * @return {boolean} True if site tracking is enabled.
 */
function isEnabled() {
	return window.wcTracks.isEnabled;
}

/**
 * Records site event.
 *
 * @param {string}  eventName       Name of the event.
 * @param {Object?} eventProperties Event properties.
 */
function recordEvent( eventName, eventProperties ) {
	// Wc-admin track script is enqueued after ours, wrap in domReady
	// to make sure we're not too early.
	domReady( () => {
		const recordFunction =
			window.wc?.tracks?.recordEvent ?? window.wcTracks.recordEvent;
		recordFunction( eventName, eventProperties );
	} );
}

const events = {
	CONNECT_ACCOUNT_CLICKED: 'wcpay_connect_account_clicked',
	CONNECT_ACCOUNT_VIEW: 'page_view',
	CONNECT_ACCOUNT_LEARN_MORE: 'wcpay_welcome_learn_more',
	CONNECT_ACCOUNT_STRIPE_CONNECTED: 'wcpay_stripe_connected',
	UPE_ENABLED: 'wcpay_upe_enabled',
	UPE_DISABLED: 'wcpay_upe_disabled',
	MULTI_CURRENCY_ENABLED_CURRENCIES_UPDATED:
		'wcpay_multi_currency_enabled_currencies_updated',
	PAYMENT_REQUEST_SETTINGS_CHANGE: 'wcpay_payment_request_settings_change',
};

export default {
	isEnabled,
	recordEvent,
	events,
};
