/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';
import { getResourceId } from '../util';
import { ID_PREFIX } from '../constants';

const defaultState = { summary: {} };

const receiveTransactions = ( state = defaultState, { type, query = {}, data = [], error } ) => {
	const index = getResourceId( ID_PREFIX.transactions, query );

	switch ( type ) {
		case TYPES.SET_TRANSACTIONS:
			return {
				...state,
				[ index ]: {
					data: data,
				},
			};
		case TYPES.SET_ERROR_FOR_TRANSACTIONS:
			return {
				...state,
				[ index ]: {
					error: error,
				},
			};
		case TYPES.SET_TRANSACTIONS_SUMMARY:
			return {
				...state,
				summary: {
					...state.summary,
					[ index ]: {
						data: data,
					},
				},
			};
		case TYPES.SET_ERROR_FOR_TRANSACTIONS_SUMMARY:
			return {
				...state,
				summary: {
					...state.summary,
					[ index ]: {
						error: error,
					},
				},
			};
	}

	// Fallback to returning the same state.
	return state;
};

export default receiveTransactions;
