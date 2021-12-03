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
	SUBSCRIPTIONS_EMPTY_STATE_VIEW: 'wcpay_subscriptions_empty_state_view',
	SUBSCRIPTIONS_EMPTY_STATE_FINISH_SETUP:
		'wcpay_subscriptions_empty_state_finish_setup',
	SUBSCRIPTIONS_EMPTY_STATE_CREATE_PRODUCT:
		'wcpay_subscriptions_empty_state_create_product',
	SUBSCRIPTIONS_ACCOUNT_NOT_CONNECTED_PRODUCT_MODAL_FINISH_SETUP:
		'wcpay_subscriptions_account_not_connected_product_modal_finish_setup',
	SUBSCRIPTIONS_ACCOUNT_NOT_CONNECTED_PRODUCT_MODAL_DISMISS:
		'wcpay_subscriptions_account_not_connected_product_modal_dismiss',
};

export default {
	isEnabled,
	recordEvent,
	events,
};
