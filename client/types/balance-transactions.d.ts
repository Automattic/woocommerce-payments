export interface BalanceTransaction {
	id?: string;
	currency: string;
	amount: number;
	fee: number;
	reporting_category?:
		'dispute' | 'dispute_reversal' | 'partial_capture_reversal' | string;
}
