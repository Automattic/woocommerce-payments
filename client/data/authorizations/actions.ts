/** @format */

/**
 * Internal Dependencies
 */
import { Query } from '@woocommerce/navigation';
import TYPES from './action-types';
import {
	AuthorizationsSummary,
	Authorization,
} from 'wcpay/types/authorizations';

export function updateAuthorizations(
	query: Query,
	data: Array< Authorization >
): {
	type: string;
	data: Array< Authorization >;
	query: Query;
} {
	return {
		type: TYPES.SET_AUTHORIZATIONS,
		data,
		query,
	};
}

export function updateErrorForAuthorizations(
	query: Query,
	error: Error
): {
	type: string;
	query: Query;
	error: Error;
} {
	return {
		type: TYPES.SET_ERROR_FOR_AUTHORIZATIONS,
		query,
		error,
	};
}

export function updateAuthorizationsSummary(
	query: Query,
	data: AuthorizationsSummary
): {
	type: string;
	data: AuthorizationsSummary;
	query: Query;
} {
	return {
		type: TYPES.SET_AUTHORIZATIONS_SUMMARY,
		data,
		query,
	};
}

export function updateErrorForAuthorizationsSummary(
	query: Query,
	error: Error
): {
	type: string;
	query: Query;
	error: Error;
} {
	return {
		type: TYPES.SET_ERROR_FOR_AUTHORIZATIONS_SUMMARY,
		query,
		error,
	};
}
