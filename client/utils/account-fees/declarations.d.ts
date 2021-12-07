/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

type TransactionPaymentMethodName =
	| 'bancontact'
	| 'card'
	| 'card_present'
	| 'giropay'
	| 'ideal'
	| 'p24'
	| 'sepa_debit'
	| 'sofort';

interface BaseFee {
	currency: string; // TODO: Should be a type, not string
	percentage_rate: number;
	fixed_rate: number;
}

interface DiscountFee {
	end_time: number;
	volumen_allowance: number;
	volume_currency: number;
	current_volume: number;
	percentage_rate: number;
	fixed_rate: number;
	discount?: number;
}

interface Fee {
	additional: BaseFee;
	base: BaseFee;
	discount: DiscountFee[];
	fx: BaseFee;
}

declare module 'wordpress-element';
