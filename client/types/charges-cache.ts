export interface ChargeCache {
	charge_id: string;
	transaction_id: string;
	amount: number;
	net: number;
	amount_captured: number;
	amount_refunded: number;
	is_captured: boolean;
	created: string;
	modified: string;
	channel: string;
	source: string;
	source_identifier: string;
	customer_name: string;
	customer_email: string;
	customer_country: string;
	fees: number;
	currency: string;
	risk_level: number;
	payment_intent_id: string;
	refunded: boolean;
	order_id: number;
	outcome_type: string;
	status: string;
}
