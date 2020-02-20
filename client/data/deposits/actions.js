/** @format */

/**
 * Internal Dependencies
 */
import TYPES from './action-types';

export function updateDeposits( query, data ) {
	return {
		type: TYPES.SET_DEPOSITS,
		query,
		data,
	};
}

export function updateErrorForDepositQuery( query, data, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_DEPOSIT_QUERY,
		query,
		data,
		error,
	};
}
