/** @format */

export interface PaymentActivityData {
	/** The currency code for the amounts below, e.g. `usd` */
	currency: string;
	/** Total payment volume amount */
	total_payment_volume: number;
	/** Charges total amount */
	charges: number;
	/** Fees total amount */
	fees: number;
	/** Disputes total amount */
	disputes: number;
	/** Refunds total amount */
	refunds: number;
	/** The timezone used to calculate the date range, e.g. 'UTC' */
	timezone: string;
	/** The date range start date */
	date_start: string;
	/** The date range end date */
	date_end: string;
	/** The interval used to calculate transaction data, e.g. 'daily' */
	interval: string;
}

export interface PaymentActivityState {
	paymentActivityData?: PaymentActivityData;
	isLoading?: boolean;
}

export interface PaymentActivityAction {
	type: string;
	data: PaymentActivityData;
}

export interface PaymentActivityQuery {
	date_start: string;
	date_end: string;
	timezone?: string;
}
