/**
 * @format
 */

/**
 * Internal dependencies
 */
import TYPES from './action-types';

const receiveTransactions = ( state = {}, { type, page, data = [], error } ) => {
	const newState = { pages: { ...state.pages }, transactions: { ...state.transactions } };
	switch ( type ) {
		case TYPES.SET_TRANSACTIONS_FOR_PAGE:
			newState.pages[ page ] = data.map( txn => txn.id );
			data.forEach( txn => newState.transactions[ txn.id ] = txn );
			state = newState;
			break;
		case TYPES.SET_ERROR_FOR_PAGE:
			newState.pages[ page ] = error;
			state = newState;
			break;
	}
	return state;
};

export default receiveTransactions;
