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

export function updateFraudOutcomeTransactions( status, query, data ) {
	return {
		type: TYPES.SET_FRAUD_OUTCOME_TRANSACTIONS,
		status,
		query,
		data,
	};
}

export function updateErrorForFraudOutcomeTransactions( status, query, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_FRAUD_OUTCOME_TRANSACTIONS,
		status,
		query,
		data: null,
		error,
	};
}

export function updateFraudOutcomeTransactionsSummary( status, query, data ) {
	return {
		type: TYPES.SET_FRAUD_OUTCOME_TRANSACTIONS_SUMMARY,
		status,
		query,
		data,
	};
}

export function updateErrorForFraudOutcomeTransactionsSummary(
	status,
	query,
	error
) {
	return {
		type: TYPES.SET_ERROR_FOR_FRAUD_OUTCOME_TRANSACTIONS_SUMMARY,
		status,
		query,
		data: null,
		error,
	};
}
