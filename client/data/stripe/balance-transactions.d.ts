/**
 * Internal dependencies
 */
import type { Charge } from './charges';

export type BalanceTransactionType =
	| 'adjustment'
	| 'advance'
	| 'advance_funding'
	| 'anticipation_repayment'
	| 'application_fee'
	| 'application_fee_refund'
	| 'charge'
	| 'connect_collection_transfer'
	| 'contribution'
	| 'issuing_authorization_hold'
	| 'issuing_authorization_release'
	| 'issuing_dispute'
	| 'issuing_transaction'
	| 'payment'
	| 'payment_failure_refund'
	| 'payment_refund'
	| 'payout'
	| 'payout_cancel'
	| 'payout_failure'
	| 'refund'
	| 'refund_failure'
	| 'reserve_transaction'
	| 'reserved_funds'
	| 'stripe_fee'
	| 'stripe_fx_fee'
	| 'tax_fee'
	| 'topup'
	| 'topup_reversal'
	| 'transfer'
	| 'transfer_cancel'
	| 'transfer_failure'
	| 'transfer_refund';

export interface FeeDetail {
	amount: number;
	application?: string;
	currency: string;
	description?: string;
	type: string;
}

export interface BalanceTransaction {
	id: string;
	object: 'balance_transaction';
	amount: number;
	/* eslint-disable-next-line camelcase */
	available_on: number;
	created: number;
	currency: string;
	description: string | null;
	/* eslint-disable-next-line camelcase */
	exchange_rate: number | null;
	fee: number;
	/* eslint-disable-next-line camelcase */
	fee_details: FeeDetail[];
	net: number;
	/* eslint-disable-next-line camelcase */
	reporting_category: string;
	source?: string | Charge;
	status: string;
	type: BalanceTransactionType;
}
