/** @format */

/**
 * Internal dependencies
 */

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

export const getAvailableCurrencies = ( state ) => {
	const available = getMultiCurrencyState( state ).available || [];
	return available;
};

export const getEnabledCurrencies = ( state ) => {
	const enabled = getMultiCurrencyState( state ).enabled || [];
	return enabled;
};
