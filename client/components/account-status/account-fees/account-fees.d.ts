/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

export type FeePaymentMethod =
	| 'bancontact'
	| 'card'
	| 'card_present'
	| 'giropay'
	| 'ideal'
	| 'p24'
	| 'sepa_debit'
	| 'sofort';

export interface BaseFee {
	currency: string;
	percentage_rate: number;
	fixed_rate: number;
}

export interface DiscountFee {
	end_time?: string;
	volume_allowance?: number;
	volume_currency?: string;
	current_volume?: number;
	percentage_rate: number;
	fixed_rate: number;
	discount?: number;
}

export interface FeeStructure {
	additional?: BaseFee;
	base: BaseFee;
	discount: DiscountFee[];
	fx?: BaseFee;
}
