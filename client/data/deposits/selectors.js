/** @format */

/**
 * Internal dependencies
 */
import { getResourceId } from 'utils/data';

/**
 * Retrieves the deposits state from the wp.data store if the state
 * has been initialized, otherwise returns an empty state.
 *
 * @param {Object} state Current wp.data state.
 *
 * @return {Object} The deposits state.
 */
const getDepositsState = ( state ) => {
	if ( ! state ) {
		return {};
	}

	return state.deposits || {};
};

export const getDeposit = ( state, id ) => {
	const depositById = getDepositsState( state ).byId || {};
	return depositById[ id ];
};

export const getDepositsOverview = ( state ) => {
	const DepositsOverview = getDepositsState( state ).overview || {};
	return DepositsOverview.data;
};

export const getDepositsOverviewError = ( state ) => {
	const DepositsOverview = getDepositsState( state ).overview || {};
	return DepositsOverview.error;
};

/**
 * Retrieves the deposits corresponding to the provided query or a sane
 * default if they don't exist.
 *
 * @param {Object} state Current wp.data state.
 * @param {Object} query The deposits query.
 *
 * @return {Object} The list of deposits for the given query.
 */
const getDepositsForQuery = ( state, query ) => {
	const index = getResourceId( query );
	const queries = getDepositsState( state ).queries || {};
	return queries[ index ] || {};
};

export const getDeposits = ( state, query ) => {
	const ids = getDepositsForQuery( state, query ).data || [];
	return ids.map( getDeposit.bind( this, state ) );
};

export const getDepositsCount = ( state ) => {
	return getDepositsState( state ).count;
};

export const getDepositQueryError = ( state, query ) => {
	return getDepositsForQuery( state, query ).error || {};
};

/**
 * Retrieves the deposits summary corresponding to the provided query.
 *
 * @param {Object} state Current wp.data state.
 * @param {Object} query The deposits summary query.
 *
 * @return {Object} The deposits summary for the given query.
 */
const getDepositsSummaryForQuery = ( state, query ) => {
	const index = getResourceId( query );
	const summary = getDepositsState( state ).summary || {};
	return summary[ index ] || {};
};

export const getDepositsSummary = ( state, query ) => {
	return getDepositsSummaryForQuery( state, query ).data || {};
};

export const getDepositsSummaryError = ( state, query ) => {
	return getDepositsSummaryForQuery( state, query ).error || {};
};

export const getInstantDeposit = ( state ) => {
	const instantDeposit = getDepositsState( state ).instant || {};
	return instantDeposit.data;
};
