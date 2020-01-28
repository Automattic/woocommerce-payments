/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';

const defaultState = { data: [], summary: {} };

const receiveTransactions = ( state = defaultState, { type, data = [], error } ) => {
	switch ( type ) {
		case TYPES.SET_TRANSACTIONS:
			state = {
				...state,
				data: state.data.concat( data ),
			};
			break;
		case TYPES.SET_ERROR_FOR_TRANSACTIONS:
			state = {
				...state,
				error: error,
			};
			break;
		case TYPES.SET_TRANSACTIONS_SUMMARY:
			state = {
				...state,
				summary: {
					data: data,
				},
			};
			break;
		case TYPES.SET_ERROR_FOR_TRANSACTIONS_SUMMARY:
			state = {
				...state,
				summary: {
					error: error,
				},
			};
			break;
	}
	return state;
};

export default receiveTransactions;
