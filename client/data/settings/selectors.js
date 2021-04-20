/** @format */

/**
 * Retrieves the settings state from the wp.data store if the state
 * has been initialized, otherwise returns an empty state.
 *
 * @param {Object} state Current wp.data state.
 *
 * @return {Object} The settings state.
 */
const getSettingsState = ( state ) => {
	if ( ! state ) {
		return {};
	}

	return state.settings || {};
};

export const getSettings = ( state ) => {
	return getSettingsState( state ) || {};
};
