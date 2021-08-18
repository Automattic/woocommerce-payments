/** @format */

/**
 * Internal dependencies
 */
import { getResourceId } from 'utils/data';

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
	const ids = getDisputesForQuery( state, query ).data || [];
	return ids.map( getDispute.bind( this, state ) );
};

export const getIsSavingEvidenceForDispute = ( state, id ) => {
	return (
		getDisputesState( state )?.isSavingEvidenceForDispute?.[ id ] ?? false
	);
};
