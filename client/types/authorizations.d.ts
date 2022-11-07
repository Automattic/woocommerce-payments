/**
 * External Dependencies
 */

import { Query } from '@woocommerce/navigation';

export type RiskLevel = 'high' | 'elevated' | 'normal';

export interface Authorization {
	captured: boolean;
	charge_id: string;
	created: string;
	order_id: number;
	risk_level: number;
	amount: number;
	customer_name: string;
	customer_email: string;
	customer_country: string;
	payment_intent_id: string;
	currency: string;
}

interface Authorizations {
	authorizations: Authorization[];
	authorizationsError?: string;
	isLoading: boolean;
}

export interface AuthorizationsSummary {
	count?: number;
	total?: number;
	currency?: string;
	all_currencies?: string[];
}

export interface AuthorizationsState {
	summary: {
		[ x: string ]: { data?: AuthorizationsSummary; error?: string };
	};
	byId: Record< string, Authorization >;
}

export interface GetAuthorizationsApiResponse {
	data: Authorization[];
}

export interface CaptureAuthorizationApiResponse {
	id: string;
	status: string;
}

export interface GetAuthorizationApiResponse {
	payment_intent_id: string;
	is_captured: boolean;
}

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
