/* eslint-disable camelcase */

/**
 * External dependencies
 */
import type { Address, PaymentMethod, Card } from '@stripe/stripe-js';

/**
 * Internal dependencies
 */
import type { WCPayBalanceTransaction } from '../balance-transactions/types';

/* eslint-disable-next-line @typescript-eslint/no-empty-interface */
export interface WCPayAddress extends Address {}

export interface WCPayBillingDetails {
	address?: WCPayAddress;
	email?: string;
	name?: string;
	phone?: string;
}

/* eslint-disable-next-line @typescript-eslint/no-empty-interface */
export interface WCPayPaymentMethod extends PaymentMethod {}

// We don't extend WCPayPaymentMethod here even though that's /technically/ what we're doing,
// because we have to make `card` optional if we do, even though we know that's not possible here.
export interface CardPaymentMethod {
	type: 'card';
	card: Card;
}

export interface WCPayCharge {
	id: string;
	amount: number;
	amount_captured: number;
	amount_refunded: number;
	balance_transaction: string | WCPayBalanceTransaction;
	billing_details: WCPayBillingDetails;
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
	// Including CardPaymentMethod so we can type match by checking the `type` property.
	payment_method_details?: WCPayPaymentMethod | CardPaymentMethod;
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
	data?: WCPayCharge;
	error?: Error;
}
