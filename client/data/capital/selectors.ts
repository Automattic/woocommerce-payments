/** @format */

/**
 * Internal dependencies
 */
import { ApiError } from '../../types/errors';
import { State } from '../types';
import { CapitalLoan, CapitalState, Summary } from './types';

/**
 * Retrieves the Capital loans state from the wp.data store if the state
 * has been initialized, otherwise returns an empty state.
 *
 * @param {Object} state Current wp.data state.
 *
 * @return {Object} The Capital loans state.
 */
const getCapitalState = ( state: State ): CapitalState => {
	if ( ! state ) {
		return {};
	}

	return state.capital || {};
};

export const getActiveLoanSummary = ( state: State ): Summary | undefined => {
	return getCapitalState( state ).summary;
};

export const getActiveLoanSummaryError = (
	state: State
): ApiError | undefined => {
	return getCapitalState( state ).summaryError;
};

export const getLoans = ( state: State ): CapitalLoan[] => {
	return getCapitalState( state ).loans || [];
};

export const getLoansError = ( state: State ): ApiError | undefined => {
	return getCapitalState( state ).loansError;
};
