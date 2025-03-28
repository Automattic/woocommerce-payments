/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

export interface PaymentMethodMapEntry {
	id: string;
	label: string;
	description: string;
	icon: ReactImgFuncComponent;
	currencies: string[];
	stripe_key: string;
	allows_manual_capture: boolean;
	allows_pay_later: boolean;
	accepts_only_domestic_payment: boolean;
}
