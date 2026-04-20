export interface TransactionDetails {
	/** The amount of the transaction in the store's currency */
	store_amount: number;
	/** The store's currency */
	store_currency: string;
	/** The amount of the transaction in the customer's currency */
	customer_amount: number;
	/** The customer's currency */
	customer_currency: string;
	/** The fee of the transaction in the store's currency */
	store_fee: number;
	/** The fee of the transaction in the customer's currency */
	customer_fee: number;
	/**
	 * The amount of the transaction that was captured in the store's currency
	 */
	store_amount_captured: number;
	/**
	 * The amount of the transaction that was captured in the customer's
	 * currency
	 */
	customer_amount_captured: number;
}

export interface FeeRate {
	/** The type of fee, e.g. "base", "additional", "discount" */
	type: string;
	/**
	 * The additional type of fee, e.g. "international", "fx". At the moment
	 * it is used if `type` is "additional", otherwise it is empty.
	 */
	additional_type?: string;
	/** The percentage rate of the fee, as a share of 1, e.g. 0.029 for 2.9% */
	percentage_rate: number;
	/** The fixed rate of the fee in the minimum unit of the currency, e.g. 30 for USD 0.30 */
	fixed_rate: number;
	/** The currency of the fee, e.g. "USD" */
	currency: string;
	/** The tax information associated with this fee, if applicable */
	tax?: {
		/** The tax description code (e.g. "US VAT", "JP JCT") that will be localized */
		description?: string;
		/** The tax percentage rate as a decimal (e.g. 0.21 for 21%). Must be between 0 and 1 */
		percentage_rate?: number;
	};
}
