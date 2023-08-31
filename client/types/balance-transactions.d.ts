export interface BalanceTransaction {
	currency: string;
	amount: number;
	fee: number;
	reporting_category?: 'dispute' | 'dispute_reversal' | string;
}
