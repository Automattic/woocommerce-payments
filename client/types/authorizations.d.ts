export type RiskLevel = 'high' | 'elevated' | 'normal';

// TODO: refine this type with more detailed information.
export interface Authorization {
	captured: boolean;
	charge_id: string;
	created: string;
	order_id: number;
	risk_level: RiskLevel | string;
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
	summary: {
		[ x: string ]: { data?: AuthorizationsSummary; error?: string };
	};
	byId: Record< string, Authorization >;
}

interface AuthorizationsList {
	data: Authorization[];
}
