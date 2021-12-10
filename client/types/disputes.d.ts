// eslint-disable-next-line wpcalypso/import-docblock
import { Charge } from './charges';

interface Evidence {
	[ key: string ]:
		| string
		| Record< string, boolean >
		| Record< string, string >;
	isUploading: Record< string, boolean >;
	metadata: Record< string, string >;
	uploadingErrors: Record< string, string >;
}

interface EvidenceDetails {
	has_evidence: boolean;
	due_by: number;
	submission_count: number;
}

export type ReasonType =
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

interface Order {
	customer_url: string;
	number: string;
	url: string;
	subscriptions: Array< Record< string, string > >;
}

export interface Dispute {
	status: string;
	id: string;
	evidence_details?: EvidenceDetails;
	metadata: Record< string, string >;
	productType: string;
	order?: Order;
	evidence: Evidence;
	fileSize?: Record< string, number >;
	reason: ReasonType;
	charge: Charge;
	amount: number;
	currency: string;
	created: number;
	payment_intent: string;
	object: string;
	is_charge_refundable: boolean;
	livemode: boolean;
	balance_transaction: number | null;
	balance_transactions: Array< Record< string, string > >;
}
