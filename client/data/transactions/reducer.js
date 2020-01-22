/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';
import { getResourceId } from '../util';
import { ID_PREFIX } from '../constants';

const defaultState = { summary: {} };

const receiveTransactions = ( state = defaultState, { type, query = {}, data = [], error } ) => {
	switch ( type ) {
		case TYPES.SET_TRANSACTIONS:
			state = {
				...state,
				[ getResourceId( ID_PREFIX.transactions, query ) ]: data,
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
