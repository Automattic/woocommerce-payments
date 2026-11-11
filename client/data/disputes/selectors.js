/** @format */

/**
 * Internal dependencies
 */
import { getResourceId } from 'utils/data';

const EMPTY_OBJECT = {};
const EMPTY_ARRAY = [];
const disputesCache = new WeakMap();

/**
 * Retrieves the disputes state from the wp.data store if the state
 * has been initialized, otherwise returns an empty state.
 *
 * @param {Object} state Current wp.data state.
 *
 * @return {Object} The disputes state.
 */
const getDisputesState = ( state ) => {
	if ( ! state ) {
		return {};
	}

	return state.disputes || {};
};

export const getDispute = ( state, id ) => {
	const disputeById = getDisputesState( state ).byId || {};
	return disputeById[ id ];
};

export const getDisputeError = ( state, id ) => {
	const disputeById = getDisputesState( state ).byId || {};
	return disputeById[ id ]?.error;
};

export const getCachedDispute = ( state, id ) => {
	const disputeById = getDisputesState( state ).cached || {};
	return disputeById[ id ];
};

/**
 * Retrieves the disputes corresponding to the provided query or a sane
 * default if they don't exist.
 *
 * @param {Object} state Current wp.data state.
 * @param {Object} query The disputes query.
 *
 * @return {Object} The list of disputes for the given query.
 */
const getDisputesForQuery = ( state, query ) => {
	const index = getResourceId( query );
	const queries = getDisputesState( state ).queries || {};

	return queries[ index ] || {};
};

export const getDisputes = ( state, query ) => {
	const ids = getDisputesForQuery( state, query ).data;
	if ( ! ids ) {
		return EMPTY_ARRAY;
	}

	const { cached } = getDisputesState( state );
	const previous = disputesCache.get( ids );
	if ( previous?.cached === cached ) {
		return previous.disputes;
	}

	const disputes = ids.map( ( id ) => getCachedDispute( state, id ) );
	disputesCache.set( ids, { cached, disputes } );
	return disputes;
};

/**
 * Retrieves the disputes summary corresponding to the provided query.
 *
 * @param {Object} state Current wp.data state.
 * @param {Object} query The disputes summary query.
 *
 * @return {Object} The disputes summary for the given query.
 */
const getDisputesSummaryForQuery = ( state, query ) => {
	const index = getResourceId( query );
	const summary = getDisputesState( state ).summary || {};

	return summary[ index ] || {};
};

export const getDisputesSummary = ( state, query ) => {
	return getDisputesSummaryForQuery( state, query ).data || EMPTY_OBJECT;
};
