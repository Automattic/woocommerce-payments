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
