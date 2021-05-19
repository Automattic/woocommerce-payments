/** @format */

/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import TYPES from './action-types';

const defaultState = {};

const receiveMultiCurrencies = (
	state = defaultState,
	{ type, data = [] }
) => {
	switch ( type ) {
		case TYPES.SET_AVAILABLE_CURRENCIES:
			return {
				...state,
				available: data,
			};
		case TYPES.SET_ENABLED_CURRENCIES:
			return {
				...state,
				enabled: data,
			};
	}

	return state;
};

export default receiveMultiCurrencies;
