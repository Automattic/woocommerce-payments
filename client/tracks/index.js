/**
 * External dependencies
 */
import domReady from '@wordpress/dom-ready';
import { getConfig } from 'wcpay/utils/checkout';
import { getPaymentRequestData } from 'wcpay/payment-request/utils';

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
 * @param {string}  eventName        Name of the event.
 * @param {Object} [eventProperties] Event properties (optional).
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

/**
 * Records events from buyers (aka shoppers).
 *
 * @param {string}  eventName         Name of the event.
 * @param {Object}  [eventProperties] Event properties (optional).
 */
function recordUserEvent( eventName, eventProperties ) {
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
}

const events = {
	CONNECT_ACCOUNT_CLICKED: 'wcpay_connect_account_clicked',
	CONNECT_ACCOUNT_VIEW: 'page_view',
	CONNECT_ACCOUNT_LEARN_MORE: 'wcpay_welcome_learn_more',
	CONNECT_ACCOUNT_STRIPE_CONNECTED: 'wcpay_stripe_connected',
	CONNECT_ACCOUNT_KYC_MODAL_OPENED: 'wcpay_connect_account_kyc_modal_opened',
	OVERVIEW_DEPOSITS_VIEW_HISTORY_CLICK:
		'wcpay_overview_deposits_view_history_click',
	OVERVIEW_DEPOSITS_CHANGE_SCHEDULE_CLICK:
		'wcpay_overview_deposits_change_schedule_click',
	SETTINGS_DEPOSITS_SCHEDULE_DISABLED_DOCS_CLICK:
		'wcpay_settings_deposits_schedule_disabled_docs_click',
	SETTINGS_DEPOSITS_WAITING_PERIOD_DOCS_CLICK:
		'wcpay_settings_deposits_waiting_period_docs_click',
	SETTINGS_DEPOSITS_MANAGE_IN_STRIPE_CLICK:
		'wcpay_settings_deposits_manage_in_stripe_click',
	SETTINGS_DEPOSITS_SCHEDULE_CHANGE:
		'wcpay_settings_deposits_schedule_change',
	SETTINGS_DEPOSITS_PENDING_SCHEDULES_DOCS_CLICK:
		'wcpay_settings_deposits_pending_schedules_docs_click',
	MULTI_CURRENCY_ENABLED_CURRENCIES_UPDATED:
		'wcpay_multi_currency_enabled_currencies_updated',
	PAYMENT_REQUEST_SETTINGS_CHANGE: 'wcpay_payment_request_settings_change',
	// WCPay Subscriptions empty state - prompts to connect to WCPay or create product.
	SUBSCRIPTIONS_EMPTY_STATE_VIEW: 'wcpay_subscriptions_empty_state_view',
	SUBSCRIPTIONS_EMPTY_STATE_FINISH_SETUP:
		'wcpay_subscriptions_empty_state_finish_setup',
	SUBSCRIPTIONS_EMPTY_STATE_CREATE_PRODUCT:
		'wcpay_subscriptions_empty_state_create_product',
	// WCPay Subscriptions create product modal - prompts to connect to WCPay.
	SUBSCRIPTIONS_ACCOUNT_NOT_CONNECTED_PRODUCT_MODAL_VIEW:
		'wcpay_subscriptions_account_not_connected_product_modal_view',
	SUBSCRIPTIONS_ACCOUNT_NOT_CONNECTED_PRODUCT_MODAL_FINISH_SETUP:
		'wcpay_subscriptions_account_not_connected_product_modal_finish_setup',
	SUBSCRIPTIONS_ACCOUNT_NOT_CONNECTED_PRODUCT_MODAL_DISMISS:
		'wcpay_subscriptions_account_not_connected_product_modal_dismiss',
	PLATFORM_CHECKOUT_OFFERED: 'platform_checkout_offered',
	PLATFORM_CHECKOUT_OTP_START: 'platform_checkout_otp_prompt_start',
	PLATFORM_CHECKOUT_OTP_COMPLETE: 'platform_checkout_otp_prompt_complete',
	PLATFORM_CHECKOUT_OTP_FAILED: 'platform_checkout_otp_prompt_failed',
	PLATFORM_CHECKOUT_AUTO_REDIRECT: 'platform_checkout_auto_redirect',
	PLATFORM_CHECKOUT_SKIPPED: 'platform_checkout_skipped',
	PLATFORM_CHECKOUT_EXPRESS_BUTTON_OFFERED:
		'platform_checkout_express_button_offered',
	PLATFORM_CHECKOUT_EXPRESS_BUTTON_CLICKED:
		'platform_checkout_express_button_clicked',
};

export default {
	isEnabled,
	recordEvent,
	recordUserEvent,
	events,
};
