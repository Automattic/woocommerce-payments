/**
 * Internal dependencies
 */
import type { WCPayCharge } from '../charges/types';

// Copied and reworked a bit for our purposes from
// https://github.com/stripe/stripe-node/blob/e195e0723e721f65f822e802ae6e9b154fd2a0fe/types/2020-08-27/BalanceTransactions.d.ts#L131-L164
export type WCPayBalanceTransactionType =
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

// Copied and reworked a bit for our purposes from
// https://github.com/stripe/stripe-node/blob/e195e0723e721f65f822e802ae6e9b154fd2a0fe/types/2020-08-27/BalanceTransactions.d.ts#L104-L129
export interface WCPayFeeDetail {
	amount: number;
	application?: string;
	currency: string;
	description?: string;
	type: string;
}

// Copied and reworked a bit for our purposes from
// https://github.com/stripe/stripe-node/blob/e195e0723e721f65f822e802ae6e9b154fd2a0fe/types/2020-08-27/BalanceTransactions.d.ts#L8-L101
export interface WCPayBalanceTransaction {
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
	fee_details: WCPayFeeDetail[];
	net: number;
	/* eslint-disable-next-line camelcase */
	reporting_category: string;
	source?: string | WCPayCharge;
	status: string;
	type: WCPayBalanceTransactionType;
}
