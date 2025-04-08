export interface TimelineFeeRate {
	type: string;
	additional_type?: string;
	fee_id: string;
	percentage_rate: number;
	fixed_rate: number;
	currency: string;
}

export interface TimelineFeeExchangeRate {
	from_currency: string;
	to_currency: string;
	from_amount: number;
	to_amount: number;
	rate: number;
}

export interface TimelineFeeRates {
	percentage: number;
	fixed: number;
	fixed_currency: string;
	history?: Array< TimelineFeeRate >;
	fee_exchange_rate?: TimelineFeeExchangeRate;
}

export interface TimelineTransactionDetails {
	customer_currency: string;
	customer_amount: number;
	customer_amount_captured: number;
	customer_fee: number;
	store_currency: string;
	store_amount: number;
	store_amount_captured: number;
	store_fee: number;
}

export interface TimelineDeposit {
	id: string;
	arrival_date: number;
}

export interface TimelineItem {
	type: string;
	datetime: number;
	acquirer_reference_number?: string;
	acquirer_reference_number_status?: string;
	amount?: number;
	amount_captured?: number;
	amount_refunded?: number;
	currency?: string;
	deposit?: TimelineDeposit;
	dispute_id?: string;
	evidence_due_by?: number;
	failure_reason?: string;
	failure_transaction_id?: string;
	fee?: number;
	fee_rates?: TimelineFeeRates;
	loan_id?: string;
	reason?: string;
	transaction_details?: TimelineTransactionDetails;
	transaction_id?: string;
	user_id?: number;
}
