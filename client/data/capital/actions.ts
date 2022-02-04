/** @format */

/**
 * Internal Dependencies
 */
import ACTION_TYPES from './action-types';
import { Summary, UpdateSummaryAction, ErrorSummaryAction } from './types';

export function updateActiveLoanSummary( data: Summary ): UpdateSummaryAction {
	return {
		type: ACTION_TYPES.SET_ACTIVE_LOAN_SUMMARY,
		data,
	};
}

export function updateErrorForActiveLoanSummary(
	error: string
): ErrorSummaryAction {
	return {
		type: ACTION_TYPES.SET_ERROR_FOR_ACTIVE_LOAN_SUMMARY,
		error,
	};
}
