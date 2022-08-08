/** @format */

/**
 * Internal Dependencies
 */
import { Query } from '@woocommerce/navigation';
import TYPES from './action-types';
import { Authorization, AuthorizationsSummary } from './hooks';

export function updateAuthorizations(
	query: Query,
	data: Array< Authorization >
) {
	return {
		type: TYPES.SET_AUTHORIZATIONS,
		data,
		query,
	};
}

export function updateAuthorizationsSummary(
	query: Query,
	data: AuthorizationsSummary
) {
	return {
		type: TYPES.SET_AUTHORIZATIONS_SUMMARY,
		data,
		query,
	};
}
