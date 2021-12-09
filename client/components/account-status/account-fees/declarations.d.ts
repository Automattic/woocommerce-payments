/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

export type TransactionPaymentMethodName =
	| 'bancontact'
	| 'card'
	| 'card_present'
	| 'giropay'
	| 'ideal'
	| 'p24'
	| 'sepa_debit'
	| 'sofort';

export interface BaseFee {
	currency: string; // TODO: Should be a type, not string
	percentage_rate: number;
	fixed_rate: number;
}

export interface DiscountFee {
	end_time: number;
	volumen_allowance: number;
	volume_currency: number;
	current_volume: number;
	percentage_rate: number;
	fixed_rate: number;
	discount?: number;
}

export interface Fee {
	additional: BaseFee;
	base: BaseFee;
	discount: DiscountFee[];
	fx: BaseFee;
}
