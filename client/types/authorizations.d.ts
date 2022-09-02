export type RiskLevel = 'high' | 'elevated' | 'normal';

// TODO: refine this type with more detailed information.
export interface Authorization {
	authorization_id: string;
	authorized_on: string;
	capture_by: string;
	captured: boolean;
	order: OrderDetails;
	risk_level: RiskLevel;
	amount: number;
	customer_name: string;
	customer_email: string;
	customer_country: string;
	payment_intent_id: string;
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

export interface AuthorizationsState {
	summary: Record< string, Record< string, AuthorizationsSummary > >;
	byId: Record< string, Authorization >;
}
