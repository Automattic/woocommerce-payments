/** @format */

/**
 * Internal dependencies
 */
import { getResourceId } from '../util';

/**
 * Retrieves the deposits state from the wp.data store if the state
 * has been initialized, otherwise returns an empty state.
 *
 * @param {object} state Current wp.data state.
 *
 * @returns {object} The deposits state.
 */
const getDepositsState = ( state ) => {
	if ( ! state ) {
		return {};
	}

	return state.deposits || {};
};

/**
 * Retrieves the deposits corresponding to the provided query or a sane
 * default if they don't exist.
 *
 * @param {object} state Current wp.data state.
 * @param {object} query The deposits query.
 *
 * @returns {object} The list of deposits for the given query.
 */
const getDepositsForQuery = ( state, query ) => {
	const index = getResourceId( query );
	const queries = getDepositsState( state ).queries || {};
	return queries[ index ] || {};
};

export const getDeposits = ( state, query ) => {
	const ids = getDepositsForQuery( state, query ).data || [];
	const depositById = getDepositsState( state ).byId;
	return ids.map( ( id ) => depositById[ id ] );
};

export const getDepositQueryError = ( state, query ) => {
	return getDepositsForQuery( state, query ).error || {};
};
