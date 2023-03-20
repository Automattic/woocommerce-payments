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

export function updateBlockedTransactions( query, data ) {
	return {
		type: TYPES.SET_BLOCKED_TRANSACTIONS,
		query,
		data,
	};
}

export function updateErrorForBlockedTransactions( query, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_BLOCKED_TRANSACTIONS,
		query,
		data: null,
		error,
	};
}

export function updateBlockedTransactionsSummary( query, data ) {
	return {
		type: TYPES.SET_BLOCKED_TRANSACTIONS_SUMMARY,
		query,
		data,
	};
}

export function updateErrorForBlockedTransactionsSummary( query, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_BLOCKED_TRANSACTIONS_SUMMARY,
		query,
		data: null,
		error,
	};
}

export function updateOnReviewTransactions( query, data ) {
	return {
		type: TYPES.SET_ON_REVIEW_TRANSACTIONS,
		query,
		data,
	};
}

export function updateErrorForOnReviewTransactions( query, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_ON_REVIEW_TRANSACTIONS,
		query,
		data: null,
		error,
	};
}

export function updateOnReviewTransactionsSummary( query, data ) {
	return {
		type: TYPES.SET_ON_REVIEW_TRANSACTIONS_SUMMARY,
		query,
		data,
	};
}

export function updateErrorForOnReviewTransactionsSummary( query, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_ON_REVIEW_TRANSACTIONS_SUMMARY,
		query,
		data: null,
		error,
	};
}
