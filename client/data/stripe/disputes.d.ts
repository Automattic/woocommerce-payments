/* eslint-disable camelcase */

/**
 * External dependencies
 */
import type { PaymentIntent } from '@stripe/stripe-js';
/**
 * Internal dependencies
 */
import type { StripeMetadata } from './metadata';
import type { Charge } from './charges';
import type { BalanceTransaction } from './balance-transactions';
import type { File } from './files';

interface Evidence {
	access_activity_log: string | null;
	billing_address: string | null;
	cancellation_policy: string | File | null;
	cancellation_policy_disclosure: string | null;
	cancellation_rebuttal: string | null;
	customer_communication: string | File | null;
	customer_email_address: string | null;
	customer_name: string | null;
	customer_purchase_ip: string | null;
	customer_signature: string | File | null;
	duplicate_charge_documentation: string | File | null;
	duplicate_charge_explanation: string | null;
	duplicate_charge_id: string | null;
	product_description: string | null;
	receipt: string | File | null;
	refund_policy: string | File | null;
	refund_policy_disclosure: string | null;
	refund_refusal_explanation: string | null;
	service_date: string | null;
	service_documentation: string | File | null;
	shipping_address: string | null;
	shipping_carrier: string | null;
	shipping_date: string | null;
	shipping_documentation: string | File | null;
	shipping_tracking_number: string | null;
	uncategorized_file: string | File | null;
	uncategorized_text: string | null;
}

interface EvidenceDetails {
	due_by: number | null;
	has_evidence: boolean;
	past_due: boolean;
	submission_count: number;
}

type DisputeStatus =
	| 'charge_refunded'
	| 'lost'
	| 'needs_response'
	| 'under_review'
	| 'warning_closed'
	| 'warning_needs_response'
	| 'warning_under_review'
	| 'won';

interface Dispute {
	id: string;
	object: 'dispute';
	amount: number;
	balance_transactions: Array< BalanceTransaction >;
	charge: string | Charge;
	created: number;
	currency: string;
	evidence: Evidence;
	evidence_details: EvidenceDetails;
	is_charge_refundable: boolean;
	livemode: boolean;
	metadata: StripeMetadata;
	network_reason_code?: string | null;
	payment_intent: string | PaymentIntent | null;
	reason: string;
	status: DisputeStatus;
}
