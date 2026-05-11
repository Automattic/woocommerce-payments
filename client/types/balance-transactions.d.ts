export interface BalanceTransaction {
	currency: string;
	amount: number;
	fee: number;
	reporting_category?: 'dispute' | 'dispute_reversal' | string;
	/**
	 * Unix timestamp (seconds since epoch) when the balance transaction posted.
	 * Stripe always sends this; the field is optional here for back-compat with
	 * mocks/fixtures that omit it.
	 */
	created?: number;
}
