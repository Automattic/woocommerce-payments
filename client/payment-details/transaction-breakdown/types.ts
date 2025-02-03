export interface TransactionDetails {
	store_amount: number;
	store_currency: string;
	customer_amount: number;
	customer_currency: string;
	store_fee: number;
	store_amount_captured: number;
	customer_amount_captured: number;
	customer_fee: number;
}

export interface FeeRate {
	type: string;
	additional_type?: string;
	percentage_rate: number;
	fixed_rate: number;
	currency: string;
}

// Add other type definitions as needed
