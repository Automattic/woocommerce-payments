/** @format */

const getSettingsState = ( state ) => {
	if ( ! state ) {
		return {};
	}

	return state.settings || {};
};

export const getSettings = ( state ) => {
	return getSettingsState( state ).data || {};
};

export const getEnabledPaymentMethodIds = ( state ) => {
	return getSettings( state ).enabled_payment_method_ids || [];
};

export const isSavingSettings = ( state ) => {
	return getSettingsState( state ).isSaving || false;
};
