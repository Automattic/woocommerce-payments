/** @format */

/**
 * Internal dependencies
 */
import ACTION_TYPES from './action-types';
import {
	ErrorSummaryAction,
	UpdateSummaryAction,
	ErrorLoansAction,
	UpdateLoansAction,
	CapitalState,
} from './types';

const defaultState = {};

export default (
	state: CapitalState = defaultState,
	action:
		| UpdateSummaryAction
		| ErrorSummaryAction
		| UpdateLoansAction
		| ErrorLoansAction
): CapitalState => {
	switch ( action.type ) {
		case ACTION_TYPES.SET_ACTIVE_LOAN_SUMMARY:
			return {
				...state,
				summary: action.data,
				summaryError: undefined,
			};
		case ACTION_TYPES.SET_ERROR_FOR_ACTIVE_LOAN_SUMMARY:
			return {
				...state,
				summary: undefined,
				summaryError: action.error,
			};
		case ACTION_TYPES.SET_LOANS:
			return {
				...state,
				loans: action.data,
				loansError: undefined,
			};
		case ACTION_TYPES.SET_ERROR_FOR_LOANS:
			return {
				...state,
				loans: undefined,
				loansError: action.error,
			};
	}

	return state;
};
