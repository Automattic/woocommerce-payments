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

export const getIsWCPayEnabled = ( state ) => {
	return getSettings( state ).is_wcpay_enabled || false;
};

export const getTitle = ( state ) => {
	return getSettings( state ).title || '';
};

export const getDescription = ( state ) => {
	return getSettings( state ).description || '';
};

export const getEnabledPaymentMethodIds = ( state ) => {
	return getSettings( state ).enabled_payment_method_ids || EMPTY_ARR;
};

export const getAvailablePaymentMethodIds = ( state ) => {
	return getSettings( state ).available_payment_method_ids || EMPTY_ARR;
};

export const isSavingSettings = ( state ) => {
	return getSettingsState( state ).isSaving || false;
};

export const getAccountStatementDescriptor = ( state ) => {
	return getSettings( state ).account_statement_descriptor || '';
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

export const getIsDigitalWalletsEnabled = ( state ) => {
	return getSettings( state ).is_digital_wallets_enabled || false;
};

export const getIsDebugLogEnabled = ( state ) => {
	return getSettings( state ).is_debug_log_enabled || false;
};

export const getDigitalWalletsLocations = ( state ) => {
	return getSettings( state ).digital_wallets_enabled_locations || EMPTY_ARR;
};

export const getDigitalWalletsButtonType = ( state ) => {
	return getSettings( state ).digital_wallets_button_type || '';
};

export const getDigitalWalletsButtonSize = ( state ) => {
	return getSettings( state ).digital_wallets_button_size || '';
};

export const getDigitalWalletsButtonTheme = ( state ) => {
	return getSettings( state ).digital_wallets_button_theme || '';
};
