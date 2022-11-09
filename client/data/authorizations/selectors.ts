/** @format */

import {
	AuthorizationsSummary,
	Authorization,
} from 'wcpay/types/authorizations';

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
const getAuthorizationsForQuery = ( state: Record< string, any > ) => {
	return state.authorizations;
};

export const getAuthorizations = (
	state: Record< string, any >
): Array< Authorization > => {
	return state.authorizations?.authorizations || [];
};

export const getAuthorizationsError = (
	state: Record< string, any >
): Error => {
	return getAuthorizationsForQuery( state ).error || {};
};

export const getAuthorizationsSummary = (
	state: Record< string, any >
): AuthorizationsSummary => {
	return state.authorizations?.summary;
};
