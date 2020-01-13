/** @format */

/**
 * Internal Dependencies
 */
import TYPES from './action-types';

export function updateTransactionsForPage( page, data ) {
	return {
		type: TYPES.SET_TRANSACTIONS_FOR_PAGE,
		page,
		data,
	};
}

export function updateErrorForPage( page, data, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_PAGE,
		page,
		data,
		error,
	};
}
