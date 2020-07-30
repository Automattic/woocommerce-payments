/** @format */

/**
 * Internal dependencies
 */
import { getResourceId } from '../util';

/**
 * Retrieves the disputes state from the wp.data store if the state
 * has been initialized, otherwise returns an empty state.
 *
 * @param {object} state Current wp.data state.
 *
 * @returns {object} The disputes state.
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

/**
 * Retrieves the disputes corresponding to the provided query or a sane
 * default if they don't exist.
 *
 * @param {object} state Current wp.data state.
 * @param {object} query The disputes query.
 *
 * @returns {object} The list of disputes for the given query.
 */
const getDisputesForQuery = ( state, query ) => {
	const index = getResourceId( query );
	const queries = getDisputesState( state ).queries || {};
	return queries[ index ] || {};
};

export const getDisputes = ( state, query ) => {
	const ids = getDisputesForQuery( state, query ).data || [];
	return ids.map( getDispute.bind( this, state ) );
};
