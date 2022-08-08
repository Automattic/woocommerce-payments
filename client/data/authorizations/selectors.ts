/** @format */

import { Query } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */

/**
 * Retrieves the authorizations corresponding to the provided query or a sane
 * default if they don't exist.
 *
 * @param {Object} state Current wp.data state.
 * @param {Object} query The authorizations query.
 *
 * @return {Object} The list of authorizations for the given query.
 */
const getAuthorizationsForQuery = ( state: any, query: Query ) => {
	return state.authorizations;
};

export const getAuthorizations = ( state: any, query: Query ) => {
	return state.authorizations?.authorizations || [];
};

export const getAuthorizationsError = ( state: any, query: Query ) => {
	return getAuthorizationsForQuery( state, query ).error || {};
};

export const getAuthorizationsSummary = ( state: any, query: Query ) => {
	return state.authorizations?.summary;
};
