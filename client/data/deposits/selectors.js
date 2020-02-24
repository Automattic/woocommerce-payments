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

export const getDeposit = ( state, id ) => {
	const depositById = getDepositsState( state ).byId || {};
	return depositById[ id ];
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
	return ids.map( getDeposit.bind( this, state ) );
};

export const getDepositQueryError = ( state, query ) => {
	return getDepositsForQuery( state, query ).error || {};
};
