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
function recordEvent( eventName, eventProperties = {} ) {
	// Add `is_test_mode` property to every event.
	eventProperties.is_test_mode = window.wcpaySettings?.testMode;

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
 * @param {boolean} isLegacy Event properties (optional).
 */
function recordUserEvent( eventName, eventProperties, isLegacy = false ) {
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
	body.append( 'isLegacy', isLegacy );
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
	DEPOSITS_ROW_CLICK: 'wcpay_deposits_row_click',
	DEPOSITS_DOWNLOAD_CSV_CLICK: 'wcpay_deposits_download',
	DISPUTES_ROW_ACTION_CLICK: 'wcpay_disputes_row_action_click',
	ORDER_DISPUTE_NOTICE_BUTTON_CLICK:
		'wcpay_order_dispute_notice_action_click',
	OVERVIEW_BALANCES_CURRENCY_CLICK:
		'wcpay_overview_balances_currency_tab_click',
	OVERVIEW_DEPOSITS_VIEW_HISTORY_CLICK:
		'wcpay_overview_deposits_view_history_click',
	OVERVIEW_DEPOSITS_CHANGE_SCHEDULE_CLICK:
		'wcpay_overview_deposits_change_schedule_click',
	OVERVIEW_TASK_CLICK: 'wcpay_overview_task_click',
	SETTINGS_DEPOSITS_MANAGE_IN_STRIPE_CLICK:
		'wcpay_settings_deposits_manage_in_stripe_click',
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
	TRANSACTIONS_DOWNLOAD_CSV_CLICK: 'wcpay_transactions_download_csv_click',
	WOOPAY_OFFERED: 'woopay_offered',
	WOOPAY_OTP_START: 'woopay_otp_prompt_start',
	WOOPAY_OTP_COMPLETE: 'woopay_otp_prompt_complete',
	WOOPAY_OTP_FAILED: 'woopay_otp_prompt_failed',
	WOOPAY_AUTO_REDIRECT: 'checkout_woopay_auto_redirect',
	WOOPAY_SKIPPED: 'woopay_skipped',
	WOOPAY_BUTTON_LOAD: 'woopay_button_load',
	WOOPAY_BUTTON_CLICK: 'woopay_button_click',
	WOOPAY_SAVE_MY_INFO_CLICK: 'checkout_save_my_info_click',
	// Onboarding flow.
	ONBOARDING_FLOW_STARTED: 'wcpay_onboarding_flow_started',
	ONBOARDING_FLOW_MODE_SELECTED: 'wcpay_onboarding_flow_mode_selected',
	ONBOARDING_FLOW_STEP_COMPLETED: 'wcpay_onboarding_flow_step_completed',
	ONBOARDING_FLOW_HIDDEN: 'wcpay_onboarding_flow_hidden',
	ONBOARDING_FLOW_EXITED: 'wcpay_onboarding_flow_exited',
	ONBOARDING_FLOW_REDIRECTED: 'wcpay_onboarding_flow_redirected',
	ONBOARDING_FLOW_ELIGIBILITY_MODAL_CLOSED:
		'wcpay_onboarding_flow_eligibility_modal_closed',
};

export default {
	isEnabled,
	recordEvent,
	recordUserEvent,
	events,
};
