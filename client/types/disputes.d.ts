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
	/**
	 * Whether evidence has been staged for this dispute.
	 */
	has_evidence: boolean;
	/**
	 * Date by which evidence must be submitted in order to successfully challenge dispute.
	 */
	due_by: number;
	/**
	 * Whether the last evidence submission was submitted past the due date. Defaults to false if no evidence submissions have occurred. If true, then delivery of the latest evidence is not guaranteed.
	 */
	past_due: boolean;
	/**
	 * The number of times evidence has been submitted. Typically, the merchant may only submit evidence once.
	 */
	submission_count: number;
}

/**
 * See https://stripe.com/docs/api/disputes/object#dispute_object-issuer_evidence
 */
interface IssuerEvidence {
	/**
	 * Type of issuer evidence supplied.
	 */
	evidence_type: 'retrieval' | 'chargeback' | 'response';
	/**
	 * List of up to 5 (ID of a file upload) File-based issuer evidence.
	 */
	file_evidence: string[];
	/**
	 * Free-form, text-based issuer evidence.
	 */
	text_evidence: string;
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
	metadata: {
		/* eslint-disable @typescript-eslint/naming-convention */
		/**
		 * '1' if the dispute was closed/accepted by the merchant, '0' if the dispute was closed by Stripe.
		 */
		__closed_by_merchant?: '1' | '0';
		/**
		 * Unix timestamp of when the dispute was closed.
		 */
		__dispute_closed_at?: string;
		/**
		 * Unix timestamp of when dispute evidence was submitted.
		 */
		__evidence_submitted_at?: string;
		/* eslint-enable @typescript-eslint/naming-convention */
	};
	order: null | OrderDetails;
	evidence: Evidence;
	issuer_evidence: IssuerEvidence | null;
	fileSize?: Record< string, number >;
	reason: DisputeReason;
	charge: Charge | string;
	amount: number;
	currency: string;
	created: number;
	balance_transactions: BalanceTransaction[];
}

export interface CachedDispute {
	wcpay_disputes_cache_id: number;
	stripe_account_id: string;
	dispute_id: string;
	charge_id: string;
	amount: number;
	currency: string;
	reason: DisputeReason;
	source: string;
	order_number: number;
	order: null | OrderDetails;
	customer_name: string;
	customer_email: string;
	customer_country: string;
	status: DisputeStatus;
	created: string;
	due_by: string;
}

interface UploadFieldObject {
	key: string;
	label: string;
}

export interface FileUploadControlProps {
	field: UploadFieldObject;
	fileName: string;
	disabled?: boolean;
	isDone: boolean;
	isLoading: boolean;
	accept: string;
	error?: string;
	onFileChange( key: string, file: File ): Promise< void >;
	onFileRemove( key: string ): void;
	help?: string;
	showPreview?: boolean;
	uploadButtonLabel?: string;
	type?: string;
}

export interface DisputesSummary {
	disputesSummary: {
		count?: number;
		currencies?: string[];
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
		| 'currency'
		| 'status'
		| 'reason'
		| 'source'
		| 'order'
		| 'customerName'
		| 'customerEmail'
		| 'customerCountry'
		| 'created'
		| 'dueBy'
		| 'action';
	cellClassName?: string;
	visible?: boolean;
}
