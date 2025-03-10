/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

export type PaymentMethod =
	| 'affirm'
	| 'afterpay_clearpay'
	| 'au_becs_debit'
	| 'bancontact'
	| 'card'
	| 'card_present'
	| 'eps'
	| 'klarna'
	| 'grabpay'
	| 'giropay'
	| 'ideal'
	| 'p24'
	| 'sepa_debit'
	| 'sofort';

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
