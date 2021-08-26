/** @format */

/**
 * Retrieves the multiCurrency state from the wp.data store if the state
 * has been initialized, otherwise returns an empty state.
 *
 * @param {Object} state Current wp.data state.
 *
 * @return {Object} The multiCurrency state.
 */
const getMultiCurrencyState = ( state ) => {
	if ( ! state ) {
		return {};
	}

	return state.multiCurrency || {};
};

export const getCurrencies = ( state ) => {
	return getMultiCurrencyState( state ).currencies || [];
};

export const getAvailableCurrencies = ( state ) => {
	return getCurrencies( state ).available || {};
};

export const getEnabledCurrencies = ( state ) => {
	return getCurrencies( state ).enabled || {};
};

export const getDefaultCurrency = ( state ) => {
	return getCurrencies( state ).default || {};
};

export const getStoreSettings = ( state ) => {
	return getMultiCurrencyState( state ).settings || {};
};
