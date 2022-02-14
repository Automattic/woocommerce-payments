/** @format */

/**
 * Internal dependencies
 */
import ACTION_TYPES from './action-types';
import { ErrorSummaryAction, UpdateSummaryAction, CapitalState } from './types';

const defaultState = {};

export default (
	state: CapitalState = defaultState,
	action: UpdateSummaryAction | ErrorSummaryAction
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
	}

	return state;
};
