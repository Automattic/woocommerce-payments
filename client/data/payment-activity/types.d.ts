/** @format */

export interface PaymentActivityData {
	total_payments_volume: number; // Total payments volume
	charges: number; // Charges
	fees: number; // Fees
	disputes: number; // Disputes
	refunds: number; // Refunds
}

export interface PaymentActivityState {
	paymentActivityData?: PaymentActivityData;
	isLoading?: boolean;
}

export interface PaymentsActivityAction {
	type: string;
	data: PaymentActivityData;
}

export interface QueryDate {
	date_start: string;
	date_end: string;
}
