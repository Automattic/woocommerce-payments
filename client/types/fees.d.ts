/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

export interface BaseFee {
	currency: string;
	percentage_rate: number;
	fixed_rate: number;
}

export interface DiscountFee extends BaseFee {
	end_time: string | null;
	volume_allowance: number | null;
	volume_currency: string | null;
	current_volume: number | null;
	discount?: number;
}

export interface FeeStructure {
	additional: BaseFee;
	base: BaseFee;
	discount: DiscountFee[];
	fx: BaseFee;
}
