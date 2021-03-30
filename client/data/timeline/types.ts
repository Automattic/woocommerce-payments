/* eslint-disable camelcase */

export interface FeeRates {
	fixed: number;
	fixed_currency: string;
	percentage: number;
}

type EventType =
	| 'authorized'
	| 'authorization_voided'
	| 'authorization_expired'
	| 'captured'
	| 'partial_refund'
	| 'full_refund'
	| 'failed'
	| 'blocked' // currently no event for this
	| 'dispute_needs_response'
	| 'dispute_in_review'
	| 'dispute_won'
	| 'dispute_lost'
	| 'dispute_accepted' // set as 'lost' in the API
	| 'dispute_warning_closed'
	| 'dispute_charge_refunded';

export interface Event {
	datetime: number;
	deposit?: {
		id: string;
		// We use `strtotime()` on the PHP side for this which can return `false`.
		arrival_date: number | false;
	};
	type: EventType;
}

export interface Authorized extends Event {
	amount: number;
	currency: string;
	type: 'authorized';
}

export interface Canceled extends Event {
	amount: number;
	currency: string;
	type: 'authorization_expired' | 'authorization_voided';
	user_id?: unknown;
}

export interface Captured extends Event {
	amount: number;
	currency: string;
	// Stripe API may return null if no application fee is associated with charge.
	fee?: number | null;
	fee_rates: FeeRates;
	transaction_id: string;
	type: 'captured';
}

export interface Failed extends Event {
	amount: number;
	currency: string;
	// Stripe API may return null if no corresponding failure code exists.
	reason?: string | null;
	type: 'failed';
}

export interface Refunded {
	amount_refunded: number;
	currency: string;
	transaction_id: string;
	type: 'full_refund' | 'partial_refund';
	user_id?: unknown;
}

export type DisputeReason =
	| 'bank_cannot_process'
	| 'check_returned'
	| 'credit_not_processed'
	| 'customer_initiated'
	| 'debit_not_authorized'
	| 'duplicate'
	| 'fraudulent'
	| 'general'
	| 'incorrect_account_details'
	| 'insufficient_funds'
	| 'product_not_received'
	| 'product_unacceptable'
	| 'subscription_canceled'
	| 'unrecognized';

export interface Disputed extends Event {
	type:
		| 'dispute_needs_response'
		| 'dispute_in_review'
		| 'dispute_won'
		| 'dispute_lost'
		| 'dispute_accepted' // set as 'lost' in the API.
		| 'dispute_warning_closed'
		| 'dispute_charge_refunded';
}

export interface NeedsResponse extends Disputed {
	amount?: number;
	currency?: string;
	dispute_id: string;
	// Stripe API may send null instead of a due date to indicate bank/CC company
	// doesn't allow a response.
	evidence_due_by?: number | null;
	fee?: number;
	reason: DisputeReason;
	transaction_id?: string;
	type: 'dispute_needs_response';
}

export interface EvidenceSubmitted extends Disputed {
	user_id?: unknown;
	type: 'dispute_in_review';
}

export interface Lost extends Disputed {
	amount?: number;
	currency?: string;
	fee?: number;
	transaction_id?: string;
	type: 'dispute_lost';
	user_id?: unknown;
}

export interface Won extends Disputed {
	amount?: number;
	currency?: string;
	fee?: number;
	transaction_id?: string;
	type: 'dispute_won';
}

export interface ClosedWarning extends Disputed {
	type: 'dispute_warning_closed';
}

export interface ChargeRefunded extends Disputed {
	type: 'dispute_charge_refunded';
}

export type Timeline = Event[];
