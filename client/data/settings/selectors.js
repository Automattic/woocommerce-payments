/** @format */

const EMPTY_OBJ = {};
const EMPTY_ARR = [];

const getSettingsState = ( state ) => {
	if ( ! state ) {
		return EMPTY_OBJ;
	}

	return state.settings || EMPTY_OBJ;
};

export const getSettings = ( state ) => {
	return getSettingsState( state ).data || EMPTY_OBJ;
};

const getSupportAddressState = ( state ) => {
	return getSettings( state ).account_business_support_address || EMPTY_OBJ;
};

export const getIsWCPayEnabled = ( state ) => {
	return getSettings( state ).is_wcpay_enabled || false;
};

export const getEnabledPaymentMethodIds = ( state ) => {
	return getSettings( state ).enabled_payment_method_ids || EMPTY_ARR;
};

export const getAvailablePaymentMethodIds = ( state ) => {
	return getSettings( state ).available_payment_method_ids || EMPTY_ARR;
};

export const getPaymentMethodStatuses = ( state ) => {
	return getSettings( state ).payment_method_statuses || EMPTY_ARR;
};

export const isSavingSettings = ( state ) => {
	return getSettingsState( state ).isSaving || false;
};

export const getAccountStatementDescriptor = ( state ) => {
	return getSettings( state ).account_statement_descriptor || '';
};

export const getAccountBusinessName = ( state ) => {
	return getSettings( state ).account_business_name || '';
};

export const getAccountBusinessURL = ( state ) => {
	return getSettings( state ).account_business_url || '';
};

export const getAccountBusinessSupportAddress = ( state ) => {
	return getSettings( state ).account_business_support_address || '';
};

export const getAccountBusinessSupportAddressCountry = ( state ) => {
	return getSupportAddressState( state ).country || '';
};

export const getAccountBusinessSupportAddressLine1 = ( state ) => {
	return getSupportAddressState( state ).line1 || '';
};

export const getAccountBusinessSupportAddressLine2 = ( state ) => {
	return getSupportAddressState( state ).line2 || '';
};

export const getAccountBusinessSupportAddressCity = ( state ) => {
	return getSupportAddressState( state ).city || '';
};

export const getAccountBusinessSupportAddressState = ( state ) => {
	return getSupportAddressState( state ).state || '';
};

export const getAccountBusinessSupportAddressPostalCode = ( state ) => {
	return getSupportAddressState( state ).postal_code || '';
};

export const getAccountBusinessSupportEmail = ( state ) => {
	return getSettings( state ).account_business_support_email || '';
};

export const getAccountBusinessSupportPhone = ( state ) => {
	return getSettings( state ).account_business_support_phone || '';
};

export const getAccountBrandingLogo = ( state ) => {
	return getSettings( state ).account_branding_logo || '';
};

export const getAccountBrandingIcon = ( state ) => {
	return getSettings( state ).account_branding_icon || '';
};

export const getAccountBrandingPrimaryColor = ( state ) => {
	return getSettings( state ).account_branding_primary_color || '';
};

export const getAccountBrandingSecondaryColor = ( state ) => {
	return getSettings( state ).account_branding_secondary_color || '';
};

export const getIsManualCaptureEnabled = ( state ) => {
	return getSettings( state ).is_manual_capture_enabled || false;
};

export const getIsTestModeEnabled = ( state ) => {
	return getSettings( state ).is_test_mode_enabled || false;
};

export const getIsDevModeEnabled = ( state ) => {
	return getSettings( state ).is_dev_mode_enabled || false;
};

export const getIsPaymentRequestEnabled = ( state ) => {
	return getSettings( state ).is_payment_request_enabled || false;
};

export const getIsDebugLogEnabled = ( state ) => {
	return getSettings( state ).is_debug_log_enabled || false;
};

export const getIsMultiCurrencyEnabled = ( state ) => {
	return getSettings( state ).is_multi_currency_enabled || false;
};

export const getPaymentRequestLocations = ( state ) => {
	return getSettings( state ).payment_request_enabled_locations || EMPTY_ARR;
};

export const getPaymentRequestButtonType = ( state ) => {
	return getSettings( state ).payment_request_button_type || '';
};

export const getPaymentRequestButtonSize = ( state ) => {
	return getSettings( state ).payment_request_button_size || '';
};

export const getPaymentRequestButtonTheme = ( state ) => {
	return getSettings( state ).payment_request_button_theme || '';
};

export const getIsSavedCardsEnabled = ( state ) => {
	return getSettings( state ).is_saved_cards_enabled || false;
};

export const getSavingError = ( state ) => {
	return getSettingsState( state ).savingError;
};

export const getIsCardPresentEligible = ( state ) => {
	return getSettings( state ).is_card_present_eligible || false;
};

export const getIsWCPaySubscriptionsEnabled = ( state ) => {
	return getSettings( state ).is_wcpay_subscriptions_enabled || false;
};

export const getIsWCPaySubscriptionsEligible = ( state ) => {
	return getSettings( state ).is_wcpay_subscriptions_eligible || false;
};

export const getIsSubscriptionsPluginActive = ( state ) => {
	return getSettings( state ).is_subscriptions_plugin_active || false;
};

export const getIsPlatformCheckoutEnabled = ( state ) => {
	return getSettings( state ).is_platform_checkout_enabled || false;
};

export const getPlatformCheckoutCustomMessage = ( state ) => {
	return getSettings( state ).platform_checkout_custom_message || '';
};
