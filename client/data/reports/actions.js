/** @format */

/**
 * Internal Dependencies
 */
import TYPES from './action-types';

export function updateReportsFees( query, data ) {
	return {
		type: TYPES.SET_REPORTS_FEES,
		query,
		data,
	};
}

export function updateErrorForReportsFees( query, data, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_REPORTS_FEES,
		query,
		data,
		error,
	};
}

export function updateReportsFeesSummary( query, data ) {
	return {
		type: TYPES.SET_REPORTS_FEES_SUMMARY,
		query,
		data,
	};
}

export function updateErrorForReportsFeesSummary( query, data, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_REPORTS_FEES_SUMMARY,
		query,
		data,
		error,
	};
}
