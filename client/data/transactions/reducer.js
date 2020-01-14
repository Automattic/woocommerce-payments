/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';

const receiveTransactions = ( state = { data: [] }, { type, data = [], error } ) => {
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
	}
	return state;
};

export default receiveTransactions;
