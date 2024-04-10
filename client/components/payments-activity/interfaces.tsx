export interface DateRange {
	date_start: string; // Start date
	date_end: string; // End date
}

export interface PaymentsActivityData {
	paymentActivityData: {
		// Data for the payments activity
		total_payments_volume: number; // Total payments volume
		charges: number; // Charges
		fees: number; // Fees
		disputes: number; // Disputes
		refunds: number; // Refunds
	};
	isLoading: boolean; // Loading state of the data
}
