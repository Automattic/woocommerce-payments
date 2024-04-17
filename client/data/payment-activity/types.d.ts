/** @format */

export interface PaymentActivityData {
	total_payment_volume?: number; // Total payment volume
	charges?: number; // Charges
	fees?: number; // Fees
	disputes?: number; // Disputes
	refunds?: number; // Refunds
}

export interface PaymentActivityState {
	paymentActivityData?: PaymentActivityData;
	isLoading?: boolean;
}

export interface PaymentActivityAction {
	type: string;
	data: PaymentActivityData;
}

export interface QueryDate {
	date_start: string;
	date_end: string;
}

export interface PaymentActivityQuery {
	date_start: string;
	date_end: string;
	timezone?: string;
}
