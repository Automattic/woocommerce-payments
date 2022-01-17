// eslint-disable-next-line wpcalypso/import-docblock
import { Charge } from './charges';
import { BalanceTransaction } from './balance-transactions';
import { TableCardColumn } from '@woocommerce/components';

interface Evidence {
	[ key: string ]:
		| string
		| Record< string, boolean >
		| Record< string, string >;
}

interface EvidenceDetails {
	has_evidence?: boolean;
	due_by: number;
	submission_count?: number;
}
interface Order {
	customer_url: string;
	number: string;
	url: string;
	subscriptions: Array< Record< string, string > >;
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

export type DisputeStatus =
	| 'warning_needs_response'
	| 'warning_under_review'
	| 'warning_closed'
	| 'needs_response'
	| 'under_review'
	| 'charge_refunded'
	| 'won'
	| 'lost';

export interface Dispute {
	status: DisputeStatus;
	id: string;
	evidence_details?: EvidenceDetails;
	metadata?: Record< string, any >;
	order?: Order;
	evidence?: Evidence;
	fileSize?: Record< string, number >;
	reason: DisputeReason;
	charge?: Charge;
	amount: number;
	currency: string;
	created: number;
	balance_transactions?: BalanceTransaction[];
}

export interface CachedDispute {
	wcpay_disputes_cache_id: number;
	stripe_account_id: string;
	dispute_id: string;
	charge_id?: string;
	amount: number;
	currency: string;
	reason: DisputeReason;
	source?: string;
	order_number?: number;
	customer_name?: string;
	customer_email?: string;
	customer_country?: string;
	status: DisputeStatus;
	created: string;
	due_by: string;
}

interface UploadFieldObject {
	key: string;
	label: string;
}

export interface DisputeFileUpload {
	field: UploadFieldObject;
	fileName: string;
	disabled?: boolean;
	isDone: boolean;
	isLoading: boolean;
	accept: string;
	error?: string;
	onFileChange( key: string, file: File ): any;
	onFileRemove( key: string ): any;
	help?: string;
}

export interface DisputesSummary {
	disputesSummary: {
		count?: number;
		store_currencies?: string[];
	};
	isLoading: boolean;
}

export interface Disputes {
	disputes: Dispute[];
	isLoading: boolean;
}

export interface CachedDisputes {
	disputes: CachedDispute[];
	isLoading: boolean;
}

export interface DisputesTableHeader extends TableCardColumn {
	key:
		| 'details'
		| 'amount'
		| 'status'
		| 'reason'
		| 'source'
		| 'order'
		| 'customerName'
		| 'customerEmail'
		| 'customerCountry'
		| 'created'
		| 'dueBy';
	cellClassName?: string;
	visible?: boolean;
}
