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

export function updateErrorForTransactions( query, data, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_TRANSACTIONS,
		query,
		data,
		error,
	};
}

export function updateTransactionsSummary( query, data ) {
	return {
		type: TYPES.SET_TRANSACTIONS_SUMMARY,
		query,
		data,
	};
}

export function updateErrorForTransactionsSummary( query, data, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_TRANSACTIONS_SUMMARY,
		query,
		data,
		error,
	};
}

export function updateBlockedTransactions( data ) {
	return {
		type: TYPES.SET_BLOCKED_TRANSACTIONS,
		data,
	};
}

export function updateErrorForBlockedTransactions( error ) {
	return {
		type: TYPES.SET_ERROR_FOR_BLOCKED_TRANSACTIONS,
		data: null,
		error,
	};
}

export function updateOnReviewTransactions( data ) {
	return {
		type: TYPES.SET_ON_REVIEW_TRANSACTIONS,
		data,
	};
}

export function updateErrorForOnReviewTransactions( error ) {
	return {
		type: TYPES.SET_ERROR_FOR_ON_REVIEW_TRANSACTIONS,
		data: null,
		error,
	};
}
