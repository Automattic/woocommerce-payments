/** @format */

/**
 * Internal dependencies
 */
import { getResourceId } from 'utils/data';

const EMPTY_ARRAY = [];
const EMPTY_OBJECT = {};

const getReportsState = ( state ) => {
	if ( ! state ) {
		return { summary: EMPTY_OBJECT, balanceSummary: EMPTY_OBJECT };
	}

	return (
		state.reports || { summary: EMPTY_OBJECT, balanceSummary: EMPTY_OBJECT }
	);
};

const getReportsFeesForQuery = ( state, query ) => {
	const index = getResourceId( query );
	return getReportsState( state )[ index ] || {};
};

export const getReportsFees = ( state, query ) => {
	return getReportsFeesForQuery( state, query ).data || EMPTY_ARRAY;
};

export const getReportsFeesError = ( state, query ) => {
	return getReportsFeesForQuery( state, query ).error || EMPTY_OBJECT;
};

const getReportsFeesSummaryForQuery = ( state, query ) => {
	const index = getResourceId( query );
	return getReportsState( state ).summary[ index ] || {};
};

export const getReportsFeesSummary = ( state, query ) => {
	return getReportsFeesSummaryForQuery( state, query ).data || EMPTY_OBJECT;
};

export const getReportsFeesSummaryError = ( state, query ) => {
	return getReportsFeesSummaryForQuery( state, query ).error || EMPTY_OBJECT;
};

const getReportsBalanceSummaryForQuery = ( state, query ) => {
	const index = getResourceId( query );
	return getReportsState( state ).balanceSummary?.[ index ] || {};
};

export const getReportsBalanceSummary = ( state, query ) => {
	return (
		getReportsBalanceSummaryForQuery( state, query ).data || EMPTY_OBJECT
	);
};

export const getReportsBalanceSummaryError = ( state, query ) => {
	return (
		getReportsBalanceSummaryForQuery( state, query ).error || EMPTY_OBJECT
	);
};
