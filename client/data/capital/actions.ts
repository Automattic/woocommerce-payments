/** @format */

/**
 * Internal Dependencies
 */
import ACTION_TYPES from './action-types';
import {
	ApiError,
	Summary,
	LoansList,
	UpdateSummaryAction,
	UpdateLoansAction,
	ErrorSummaryAction,
	ErrorLoansAction,
} from './types';

export function updateActiveLoanSummary( data: Summary ): UpdateSummaryAction {
	return {
		type: ACTION_TYPES.SET_ACTIVE_LOAN_SUMMARY,
		data,
	};
}

export function updateErrorForActiveLoanSummary(
	error: ApiError
): ErrorSummaryAction {
	return {
		type: ACTION_TYPES.SET_ERROR_FOR_ACTIVE_LOAN_SUMMARY,
		error,
	};
}

export function updateLoans( data: LoansList ): UpdateLoansAction {
	return {
		type: ACTION_TYPES.SET_LOANS,
		data: data.data,
	};
}

export function updateErrorForLoans( error: ApiError ): ErrorLoansAction {
	return {
		type: ACTION_TYPES.SET_ERROR_FOR_LOANS,
		error,
	};
}
