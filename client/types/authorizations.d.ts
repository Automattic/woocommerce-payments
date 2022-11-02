/**
 * External Dependencies
 */

import { Query } from '@woocommerce/navigation';

export type RiskLevel = 'high' | 'elevated' | 'normal';

// TODO: refine this type with more detailed information.
export interface Authorization {
	authorization_id: string;
	authorized_on: string;
	capture_by: string;
	order: OrderDetails;
	risk_level: RiskLevel;
	amount: number;
	customer_name: string;
	customer_email: string;
	customer_country: string;
}

interface Authorizations {
	authorizations: Authorization[];
	authorizationsError?: string;
	isLoading: boolean;
}

export interface AuthorizationsSummary {
	count?: number;
	total?: number;
	totalAmount?: number;
	currency?: string;
	store_currencies?: string[];
	customer_currencies?: string[];
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

export interface AuthorizationsState {
	summary: {
		[ x: string ]: { data?: AuthorizationsSummary; error?: string };
	};
}
