export default {
	APPLEPAY_BUTTON_CLICK: 'applepay_button_click',
	APPLEPAY_BUTTON_LOAD: 'applepay_button_load',
	CONNECT_ACCOUNT_CLICKED: 'wcpay_connect_account_clicked',
	CONNECT_ACCOUNT_VIEW: 'page_view',
	CONNECT_ACCOUNT_LEARN_MORE: 'wcpay_welcome_learn_more',
	CONNECT_ACCOUNT_STRIPE_CONNECTED: 'wcpay_stripe_connected',
	CONNECT_ACCOUNT_KYC_MODAL_OPENED: 'wcpay_connect_account_kyc_modal_opened',
	DEPOSITS_ROW_CLICK: 'wcpay_deposits_row_click',
	DEPOSITS_DOWNLOAD_CSV_CLICK: 'wcpay_deposits_download',
	DISPUTES_ROW_ACTION_CLICK: 'wcpay_disputes_row_action_click',
	DISPUTE_CHALLENGE_CLICKED: 'wcpay_dispute_challenge_clicked',
	DISPUTE_ACCEPT_CLICK: 'wcpay_dispute_accept_click',
	DISPUTE_DOWNLOAD_CSV_CLICK: 'wcpay_disputes_download',
	DISPUTE_ACCEPT_MODAL_VIEW: 'wcpay_dispute_accept_modal_view',
	DISPUTE_PRODUCT_SELECTED: 'wcpay_dispute_product_selected',
	DISPUTE_INQUIRY_REFUND_CLICK: 'wcpay_dispute_inquiry_refund_click',
	DISPUTE_SUBMIT_EVIDENCE_CLICK: 'wcpay_dispute_submit_evidence_clicked',
	DISPUTE_SAVE_EVIDENCE_CLICK: 'wcpay_dispute_save_evidence_clicked',
	DISPUTE_SUBMIT_EVIDENCE_SUCCESS: 'wcpay_dispute_submit_evidence_success',
	DISPUTE_SAVE_EVIDENCE_SUCCESS: 'wcpay_dispute_save_evidence_success',
	DISPUTE_SUBMIT_EVIDENCE_FAILED: 'wcpay_dispute_submit_evidence_failed',
	DISPUTE_SAVE_EVIDENCE_FAILED: 'wcpay_dispute_save_evidence_failed',
	DISPUTE_INQUIRY_REFUND_MODAL_VIEW:
		'wcpay_dispute_inquiry_refund_modal_view',
	DISPUTE_NOTICE_VIEW: 'wcpay_order_dispute_notice_view',
	DISPUTE_NOTICE_CLICK: 'wcpay_order_dispute_notice_action_click',
	DISPUTE_FILE_UPLOAD_STARTED: 'wcpay_dispute_file_upload_started',
	DISPUTE_FILE_UPLOAD_SUCCESS: 'wcpay_dispute_file_upload_success',
	DISPUTE_FILE_UPLOAD_FAILED: 'wcpay_dispute_file_upload_failed',
	FRAUD_PROTECTION_BANNER_RENDERED: 'wcpay_fraud_protection_banner_rendered',
	FRAUD_PROTECTION_BANNER_LEARN_MORE_CLICKED:
		'wcpay_fraud_protection_banner_learn_more_button_clicked',
	FRAUD_PROTECTION_ORDER_DETAILS_LINK_CLICKED:
		'wcpay_fraud_protection_order_details_link_clicked',
	FRAUD_PROTECTION_TRANSACTION_REVIEWED_MERCHANT_APPROVED:
		'wcpay_fraud_protection_transaction_reviewed_merchant_approved',
	FRAUD_PROTECTION_TRANSACTION_REVIEWED_MERCHANT_BLOCKED:
		'wcpay_fraud_protection_transaction_reviewed_merchant_blocked',
	FRAUD_PROTECTION_ADVANCED_SETTINGS_SAVED:
		'wcpay_fraud_protection_advanced_settings_saved',
	FRAUD_PROTECTION_RISK_LEVEL_PRESET_ENABLED:
		'wcpay_fraud_protection_risk_level_preset_enabled',
	FRAUD_PROTECTION_BASIC_MODAL_VIEWED:
		'wcpay_fraud_protection_basic_modal_viewed',
	FRAUD_PROTECTION_TOUR_CLICKED_THROUGH:
		'wcpay_fraud_protection_tour_clicked_through',
	FRAUD_PROTECTION_TOUR_ABANDONED: 'wcpay_fraud_protection_tour_abandoned',
	FRAUD_OUTCOME_TRANSACTIONS_DOWNLOAD:
		'wcpay_fraud_outcome_transactions_download',
	GOOGLEPAY_BUTTON_CLICK: 'gpay_button_click',
	GOOGLEPAY_BUTTON_LOAD: 'gpay_button_load',
	INBOX_ACTION_DISMISSED: 'wcpay_inbox_action_dismissed',
	INBOX_ACTION_CLICK: 'wcpay_inbox_action_click',
	INBOX_NOTE_VIEW: 'wcpay_inbox_note_view',
	// Onboarding flow.
	ONBOARDING_FLOW_STARTED: 'wcpay_onboarding_flow_started',
	ONBOARDING_FLOW_MODE_SELECTED: 'wcpay_onboarding_flow_mode_selected',
	ONBOARDING_FLOW_STEP_COMPLETED: 'wcpay_onboarding_flow_step_completed',
	ONBOARDING_FLOW_HIDDEN: 'wcpay_onboarding_flow_hidden',
	ONBOARDING_FLOW_EXITED: 'wcpay_onboarding_flow_exited',
	ONBOARDING_FLOW_REDIRECTED: 'wcpay_onboarding_flow_redirected',
	ONBOARDING_FLOW_RESET: 'wcpay_onboarding_flow_reset',
	ONBOARDING_FLOW_ELIGIBILITY_MODAL_CLOSED:
		'wcpay_onboarding_flow_eligibility_modal_closed',
	OVERVIEW_BALANCES_CURRENCY_CLICK:
		'wcpay_overview_balances_currency_tab_click',
	OVERVIEW_DEPOSITS_VIEW_HISTORY_CLICK:
		'wcpay_overview_deposits_view_history_click',
	OVERVIEW_DEPOSITS_CHANGE_SCHEDULE_CLICK:
		'wcpay_overview_deposits_change_schedule_click',
	OVERVIEW_TASK_CLICK: 'wcpay_overview_task_click',
	PAYMENT_DETAILS_VIEW_DISPUTE_EVIDENCE_BUTTON_CLICKED:
		'wcpay_view_submitted_evidence_clicked',
	SETTINGS_DEPOSITS_MANAGE_IN_STRIPE_CLICK:
		'wcpay_settings_deposits_manage_in_stripe_click',
	SETTINGS_FILE_UPLOAD_STARTED: 'wcpay_merchant_settings_file_upload_started',
	SETTINGS_FILE_UPLOAD_SUCCESS: 'wcpay_merchant_settings_file_upload_success',
	SETTINGS_FILE_UPLOAD_FAILED: 'wcpay_merchant_settings_upload_failed',
	MULTI_CURRENCY_ENABLED_CURRENCIES_UPDATED:
		'wcpay_multi_currency_enabled_currencies_updated',
	PAGE_VIEW: 'page_view',
	PAYMENT_REQUEST_SETTINGS_CHANGE: 'wcpay_payment_request_settings_change',
	PLACE_ORDER_CLICK: 'checkout_place_order_button_click',
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
	TRANSACTIONS_DETAILS_REFUND_MODAL_CLOSE:
		'payments_transactions_details_refund_modal_close',
	TRANSACTIONS_DETAILS_REFUND_MODAL_OPEN:
		'payments_transactions_details_refund_modal_open',
	TRANSACTIONS_DETAILS_CAPTURE_CHARGE_BUTTON_CLICK:
		'payments_transactions_details_capture_charge_button_click',
	TRANSACTIONS_DETAILS_CANCEL_CHARGE_BUTTON_CLICK:
		'payments_transactions_details_cancel_charge_button_click',
	TRANSACTION_DETAILS_PARTIAL_REFUND:
		'payments_transactions_details_partial_refund',
	TRANSACTIONS_DETAILS_REFUND_FULL:
		'payments_transactions_details_refund_full',
	TRANSACTIONS_RISK_REVIEW_LIST_REVIEW_BUTTON_CLICK:
		'payments_transactions_risk_review_list_review_button_click',
	TRANSACTIONS_UNCAPTURED_LIST_CAPTURE_CHARGE_BUTTON_CLICK:
		'payments_transactions_uncaptured_list_capture_charge_button_click',
	WOOPAY_EMAIL_CHECK: 'checkout_email_address_woopay_check',
	WOOPAY_OFFERED: 'checkout_woopay_save_my_info_offered',
	WOOPAY_AUTO_REDIRECT: 'checkout_woopay_auto_redirect',
	WOOPAY_SKIPPED: 'woopay_skipped',
	WOOPAY_BUTTON_LOAD: 'woopay_button_load',
	WOOPAY_BUTTON_CLICK: 'woopay_button_click',
	WOOPAY_SAVE_MY_INFO_COUNTRY_CLICK:
		'checkout_woopay_save_my_info_country_click',
	WOOPAY_SAVE_MY_INFO_CLICK: 'checkout_save_my_info_click',
	WOOPAY_SAVE_MY_INFO_MOBILE_ENTER:
		'checkout_woopay_save_my_info_mobile_enter',
	WOOPAY_SAVE_MY_INFO_TOS_CLICK: 'checkout_save_my_info_tos_click',
	WOOPAY_SAVE_MY_INFO_PRIVACY_CLICK:
		'checkout_save_my_info_privacy_policy_click',
	WOOPAY_SAVE_MY_INFO_TOOLTIP_CLICK: 'checkout_save_my_info_tooltip_click',
	WOOPAY_SAVE_MY_INFO_TOOLTIP_LEARN_MORE_CLICK:
		'checkout_save_my_info_tooltip_learn_more_click',
};
