/** @format */

/**
 * Internal Dependencies
 */
import TYPES from './action-types';

export function updateDeposit( data ) {
	return {
		type: TYPES.SET_DEPOSIT,
		data,
	};
}

export function updateDepositsOverview( data ) {
	return {
		type: TYPES.SET_DEPOSITS_OVERVIEW,
		data,
	};
}

export function updateErrorForDepositsOverview( data, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_DEPOSITS_OVERVIEW,
		error,
	};
}

export function updateDeposits( query, data ) {
	return {
		type: TYPES.SET_DEPOSITS,
		query,
		data,
	};
}

export function updateDepositsCount( data ) {
	return {
		type: TYPES.SET_DEPOSITS_COUNT,
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
