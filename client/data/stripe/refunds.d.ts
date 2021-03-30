/* eslint-disable camelcase */

/**
 * External dependencies
 */
import type { PaymentIntent } from '@stripe/stripe-js';
/**
 * Internal dependencies
 */
import type { BalanceTransaction } from './balance-transactions';
import type { StripeMetadata } from './metadata';
import type { Charge } from './charges';

interface Refund {
	id: string;
	object: 'refund';
	amount: number;
	balance_transaction: string | BalanceTransaction | null;
	charge: string | Charge | null;
	created: number;
	currency: string;
	description?: string;
	failure_balance_transaction?: string | BalanceTransaction;
	failure_reason?: string;
	metadata: StripeMetadata | null;
	payment_intent: string | PaymentIntent | null;
	reason: string | null;
	receipt_number: string | null;
	source_transfer_reversal: string | unknown | null;
	status: string | null;
	transfer_reversal: string | unknown | null;
}
