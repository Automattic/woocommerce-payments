/** @format */

/**
 * Internal Dependencies
 */
import TYPES from './action-types';

export function updateTransactions( data ) {
	return {
		type: TYPES.SET_TRANSACTIONS,
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
