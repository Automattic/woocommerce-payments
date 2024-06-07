/**
 * The Event type represents the different events that can be tracked in WooPayments.
 * It also allows an arbitrary string for dynamic event names.
 *
 * @see https://github.com/Automattic/woocommerce-payments/issues/8075#issuecomment-1933823687
 * @typedef {string} Event
 */
export type Event =
	| 'applepay_button_click'
	| 'applepay_button_load'
	| 'page_view'
	| 'wcpay_connect_account_clicked'
	| 'wcpay_account_details_link_clicked'
	| 'wcpay_welcome_learn_more'
	| 'wcpay_stripe_connected'
	| 'wcpay_connect_account_kyc_modal_opened'
	| 'wcpay_deposits_row_click'
	| 'wcpay_deposits_download'
	| 'wcpay_disputes_row_action_click'
	| 'wcpay_dispute_challenge_clicked'
	| 'wcpay_dispute_accept_click'
	| 'wcpay_disputes_download'
	| 'wcpay_dispute_accept_modal_view'
	| 'wcpay_dispute_product_selected'
	| 'wcpay_dispute_inquiry_refund_click'
	| 'wcpay_dispute_submit_evidence_clicked'
	| 'wcpay_dispute_save_evidence_clicked'
	| 'wcpay_dispute_submit_evidence_success'
	| 'wcpay_dispute_save_evidence_success'
	| 'wcpay_dispute_submit_evidence_failed'
	| 'wcpay_dispute_save_evidence_failed'
	| 'wcpay_dispute_inquiry_refund_modal_view'
	| 'wcpay_order_dispute_notice_view'
	| 'wcpay_order_dispute_notice_action_click'
	| 'wcpay_dispute_file_upload_started'
	| 'wcpay_dispute_file_upload_success'
	| 'wcpay_dispute_file_upload_failed'
	| 'wcpay_fraud_protection_banner_rendered'
	| 'wcpay_fraud_protection_banner_learn_more_button_clicked'
	| 'wcpay_fraud_protection_order_details_link_clicked'
	| 'wcpay_fraud_protection_transaction_reviewed_merchant_approved'
	| 'wcpay_fraud_protection_transaction_reviewed_merchant_blocked'
	| 'wcpay_fraud_protection_advanced_settings_saved'
	| 'wcpay_fraud_protection_risk_level_preset_enabled'
	| 'wcpay_fraud_protection_basic_modal_viewed'
	| 'wcpay_fraud_protection_tour_clicked_through'
	| 'wcpay_fraud_protection_tour_abandoned'
	| 'wcpay_fraud_outcome_transactions_download'
	| 'wcpay_gateway_toggle'
	| 'gpay_button_click'
	| 'gpay_button_load'
	| 'wcpay_inbox_action_dismissed'
	| 'wcpay_inbox_action_click'
	| 'wcpay_inbox_note_view'
	| 'wcpay_onboarding_flow_started'
	| 'wcpay_onboarding_flow_step_completed'
	| 'wcpay_onboarding_flow_hidden'
	| 'wcpay_onboarding_flow_exited'
	| 'wcpay_onboarding_flow_redirected'
	| 'wcpay_onboarding_flow_reset'
	| 'wcpay_onboarding_flow_eligibility_modal_closed'
	| 'wcpay_overview_balances_currency_tab_click'
	| 'wcpay_overview_deposits_view_history_click'
	| 'wcpay_overview_deposits_change_schedule_click'
	| 'wcpay_overview_task_click'
	| 'wcpay_overview_payment_activity_click'
	| 'wcpay_view_submitted_evidence_clicked'
	| 'wcpay_settings_deposits_manage_in_stripe_click'
	| 'wcpay_merchant_settings_file_upload_started'
	| 'wcpay_merchant_settings_file_upload_success'
	| 'wcpay_merchant_settings_upload_failed'
	| 'wcpay_multi_currency_enabled_currencies_updated'
	| 'wcpay_payment_request_settings_change'
	| 'wcpay_proceed_to_checkout_button_click'
	| 'checkout_place_order_button_click'
	| 'wcpay_subscriptions_empty_state_view'
	| 'wcpay_subscriptions_empty_state_finish_setup'
	| 'wcpay_subscriptions_empty_state_create_product'
	| 'wcpay_subscriptions_account_not_connected_product_modal_view'
	| 'wcpay_subscriptions_account_not_connected_product_modal_finish_setup'
	| 'wcpay_subscriptions_account_not_connected_product_modal_dismiss'
	| 'wcpay_transactions_download_csv_click'
	| 'payments_transactions_details_refund_modal_close'
	| 'payments_transactions_details_refund_modal_open'
	| 'payments_transactions_details_capture_charge_button_click'
	| 'payments_transactions_details_cancel_charge_button_click'
	| 'payments_transactions_details_partial_refund'
	| 'payments_transactions_details_refund_full'
	| 'payments_transactions_risk_review_list_review_button_click'
	| 'payments_transactions_uncaptured_list_capture_charge_button_click'
	| 'checkout_email_address_woopay_check'
	| 'checkout_woopay_save_my_info_offered'
	| 'checkout_woopay_save_my_info_country_click'
	| 'checkout_woopay_save_my_info_mobile_enter'
	| 'checkout_save_my_info_tos_click'
	| 'checkout_save_my_info_privacy_policy_click'
	| 'checkout_save_my_info_tooltip_click'
	| 'checkout_save_my_info_tooltip_learn_more_click'
	| 'woopay_skipped'
	| 'woopay_button_load'
	| 'woopay_button_click'
	| string;
