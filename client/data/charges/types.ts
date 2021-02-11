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

export interface CardDetails {
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
}

export interface AchCreditTransfer extends PaymentMethodDetails {
	type: 'ach_credit_transfer';
	ach_credit_transfer: unknown;
}

export interface AchDebit extends PaymentMethodDetails {
	type: 'ach_debit';
	ach_debit: unknown;
}

export interface AliPay extends PaymentMethodDetails {
	type: 'alipay';
	alipay: unknown;
}

export interface AuBecsDebit extends PaymentMethodDetails {
	type: 'au_becs_debit';
	au_becs_debit: unknown;
}

export interface BacsDebit extends PaymentMethodDetails {
	type: 'bacs_debit';
	bacs_debit: unknown;
}

export interface BanContact extends PaymentMethodDetails {
	type: 'bancontact';
	bancontact: unknown;
}

export interface Card extends PaymentMethodDetails {
	type: 'card';
	card: CardDetails;
}

export interface CardPresent extends PaymentMethodDetails {
	type: 'card_present';
	card_present: unknown;
}

export interface EPS extends PaymentMethodDetails {
	type: 'eps';
	eps: unknown;
}

export interface FPX extends PaymentMethodDetails {
	type: 'fpx';
	fpx: unknown;
}

export interface GiroPay extends PaymentMethodDetails {
	type: 'giropay';
	giropay: unknown;
}

export interface GrabPay extends PaymentMethodDetails {
	type: 'grabpay';
	grabpay: unknown;
}

export interface Ideal extends PaymentMethodDetails {
	type: 'ideal';
	ideal: unknown;
}

export interface InteracPresent extends PaymentMethodDetails {
	type: 'interac_present';
	interac_present: unknown;
}

export interface Klarna extends PaymentMethodDetails {
	type: 'klarna';
	klarna: unknown;
}

export interface Multibanco extends PaymentMethodDetails {
	type: 'multibanco';
	multibanco: unknown;
}

export interface Oxxo extends PaymentMethodDetails {
	type: 'oxxo';
	oxxo: unknown;
}

export interface P24 extends PaymentMethodDetails {
	type: 'p24';
	p24: unknown;
}

export interface SepaDebit extends PaymentMethodDetails {
	type: 'sepa_debit';
	sepa_debit: unknown;
}

export interface Sofort extends PaymentMethodDetails {
	type: 'sofort';
	sofort: unknown;
}

export interface StripeAccount extends PaymentMethodDetails {
	type: 'stripe_account';
	stripe_account: unknown;
}

export interface WeChat extends PaymentMethodDetails {
	type: 'wechat';
	wechat: unknown;
}

export interface Charge {
	id: string;
	amount: number;
	amount_captured: number;
	amount_refunded: number;
	balance_transaction: string;
	billing_details: BillingDetails;
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
	payment_method_details?:
		| AchCreditTransfer
		| AchDebit
		| AliPay
		| AuBecsDebit
		| BacsDebit
		| BanContact
		| Card
		| CardPresent
		| EPS
		| FPX
		| GiroPay
		| GrabPay
		| Ideal
		| InteracPresent
		| Klarna
		| Multibanco
		| Oxxo
		| P24
		| SepaDebit
		| Sofort
		| StripeAccount
		| WeChat;
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
