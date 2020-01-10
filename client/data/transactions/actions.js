/**
 * External Dependencies
 *
 * @format
 */

/**
 * Internal Dependencies
 */
import TYPES from './action-types';

export function updateTransactionsForPage( page, data ) {
	return {
		type: TYPES.UPDATE_TRANSACTIONS_FOR_PAGE,
		page,
		data,
	};
}

export function updateErrorForPage( page, data, error ) {
	return {
		type: TYPES.UPDATE_ERROR_FOR_PAGE,
		page,
		data,
		error,
	};
}
