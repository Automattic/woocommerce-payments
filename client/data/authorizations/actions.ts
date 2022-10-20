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
	data: Authorization[],
	error: Error
): {
	type: string;
	data: Authorization[];
	query: Query;
	error: Error;
} {
	return {
		type: TYPES.SET_ERROR_FOR_AUTHORIZATIONS,
		data,
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
	data: AuthorizationsSummary,
	error: Error
): {
	type: string;
	data: AuthorizationsSummary;
	query: Query;
	error: Error;
} {
	return {
		type: TYPES.SET_ERROR_FOR_AUTHORIZATIONS_SUMMARY,
		data,
		query,
		error,
	};
}
