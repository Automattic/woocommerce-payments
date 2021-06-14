/** @format */

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
		case TYPES.SET_CURRENCIES:
			return {
				...state,
				currencies: data,
				available: data.available,
				enabled: data.enabled,
				default: data.default,
			};
		case TYPES.SET_AVAILABLE_CURRENCIES:
			return {
				...state,
				currencies: {
					...state,
					available: data.available,
				},
				available: data.available,
			};
		case TYPES.SET_ENABLED_CURRENCIES:
			return {
				...state,
				currencies: {
					...state,
					enabled: data.enabled,
				},
				enabled: data.enabled,
			};
		case TYPES.SET_DEFAULT_CURRENCY:
			return {
				...state,
				currencies: {
					...state,
					default: data.default,
				},
				default: data.default,
			};
	}

	return state;
};

export default receiveMultiCurrencies;
