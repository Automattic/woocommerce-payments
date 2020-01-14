/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';

const receiveTransactions = ( state = { objects: [] }, { type, data = [], error } ) => {
	switch ( type ) {
		case TYPES.SET_TRANSACTIONS:
			return {
				...state,
				objects: state.objects.concat( data ),
			};
		case TYPES.SET_ERROR_FOR_TRANSACTIONS:
			return {
				...state,
				errors: {
					...state.errors,
					transactions: error,
				},
			};
	}
	return state;
};

export default receiveTransactions;
