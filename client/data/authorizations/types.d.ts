/** @format */

/**
 * Internal Dependencies
 */
import { Query } from '@woocommerce/navigation';
import {
	Authorization,
	AuthorizationsSummary,
} from 'wcpay/types/authorizations';

export interface UpdateAuthorizationAction {
	type: string;
	data: Authorization;
	query?: Query;
	error?: string;
}

export interface UpdateAuthorizationsAction {
	type: string;
	data: Array< Authorization >;
	query: Query;
	error?: string;
}

export interface UpdateAuthorizationsSummaryAction {
	type: string;
	data: AuthorizationsSummary;
	query: Query;
	error?: string;
}

export interface SetErrorForAuthorizationsAction {
	type: string;
	error: string;
}

export interface SetErrorForAuthorizationsSummaryAction {
	type: string;
	error: string;
}
