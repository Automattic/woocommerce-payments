/** @format */

/**
 * Internal Dependencies
 */
import TYPES from './action-types';

export function updateTransactions( query, data ) {
	return {
		type: TYPES.SET_TRANSACTIONS,
		query,
		data,
	};
}

export function updateErrorForTransactions( data, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_TRANSACTIONS,
		data,
		error,
	};
}

export function updateTransactionsSummary( data ) {
	return {
		type: TYPES.SET_TRANSACTIONS_SUMMARY,
		data,
	};
}

export function updateErrorForTransactionsSummary( data, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_TRANSACTIONS_SUMMARY,
		data,
		error,
	};
}
