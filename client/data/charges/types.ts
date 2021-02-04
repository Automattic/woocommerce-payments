/* eslint-disable camelcase */
export interface Address {
	city?: string;
	country?: string;
	line1?: string;
	line2?: string;
	postal_code?: string;
	state?: string;
}

export interface BillingDetails {
	address?: Address;
	email?: string;
	name?: string;
	phone?: string;
}

export interface CardPaymentMethodDetails {
	brand:
		| 'amex'
		| 'diners'
		| 'discover'
		| 'jcb'
		| 'mastercard'
		| 'unionpay'
		| 'visa'
		| 'unknown';
	checks: {
		address_line1_check?: 'pass' | 'fail' | 'unavailable' | 'unchecked';
		address_postal_code_check?:
			| 'pass'
			| 'fail'
			| 'unavailable'
			| 'unchecked';
		cvc_check?: 'pass' | 'fail' | 'unavailable' | 'unchecked';
	};
	country: string;
	exp_month: number;
	exp_year: number;
	fingerprint: string;
	funding: 'credit' | 'debit' | 'prepaid' | 'unknown';
	installments?: {
		plan: {
			count: number;
			interval: 'month';
			type: 'fixed_count';
		};
	};
	last4: string;
	network:
		| 'amex'
		| 'cartes_bancaires'
		| 'diners'
		| 'discover'
		| 'interac'
		| 'jcb'
		| 'mastercard'
		| 'unionpay'
		| 'visa'
		| 'unknown';
	three_d_secure?: {
		authentication_flow: 'challenge' | 'frictionless';
		result:
			| 'authenticated'
			| 'attempt_acknowledged'
			| 'not_supported'
			| 'failed'
			| 'processing_error';
		result_reason:
			| 'card_not_enrolled'
			| 'network_not_supported'
			| 'abandoned'
			| 'canceled'
			| 'rejected'
			| 'bypassed'
			| 'protocol_error';
		version: '1.0.2' | '2.1.0' | '2.2.0';
	};
	wallet: unknown;
}

export interface PaymentMethodDetails {
	type:
		| 'ach_credit_transfer'
		| 'ach_debit'
		| 'alipay'
		| 'au_becs_debit'
		| 'bacs_debit'
		| 'bancontact'
		| 'card'
		| 'card_present'
		| 'eps'
		| 'fpx'
		| 'giropay'
		| 'grabpay'
		| 'ideal'
		| 'interac_present'
		| 'klarna'
		| 'multibanco'
		| 'oxxo'
		| 'p24'
		| 'sepa_debit'
		| 'sofort'
		| 'stripe_account'
		| 'wechat';
	ach_credit_transfer?: unknown;
	ach_debit?: unknown;
	alipay?: unknown;
	au_becs_debit?: unknown;
	bacs_debit?: unknown;
	bancontact?: unknown;
	card?: CardPaymentMethodDetails;
	card_present?: unknown;
	eps?: unknown;
	fpx?: unknown;
	giropay?: unknown;
	grabpay?: unknown;
	ideal?: unknown;
	interac_present?: unknown;
	klarna?: unknown;
	multibanco?: unknown;
	oxxo?: unknown;
	p24?: unknown;
	sepa_debit?: unknown;
	sofort?: unknown;
	stripe_account?: unknown;
	wechat?: unknown;
}

export interface Charge {
	id: string;
	amount: number;
	amount_captured: number;
	amount_refunded: number;
	balance_transaction: string;
	billing_details?: BillingDetails;
	calculated_statement_descriptor?: string;
	captured: boolean;
	created: number;
	currency: string;
	customer?: string;
	description: string;
	disputed: boolean;
	invoice?: string;
	metadata?: Record< string, unknown >;
	object: 'charge';
	paid: boolean;
	payment_intent?: string;
	payment_method?: string;
	payment_method_details?: PaymentMethodDetails;
	receipt_email?: string;
	receipt_number?: string;
	receipt_url?: string;
	refunded: boolean;
	refunds?: {
		object: 'list';
		data: unknown;
		has_more: boolean;
		url: string;
	};
	review?: string;
	shipping?: unknown;
	source_transfer?: unknown;
	statement_descriptor?: string;
	statement_descriptor_suffix?: string;
	status: 'pending' | 'succeeded' | 'failed';
}

export interface ChargesStateEntry {
	data?: Charge;
	error?: Error;
}
