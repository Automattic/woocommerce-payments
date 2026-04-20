/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import { Dispute } from 'wcpay/types/disputes';
import { Charge } from 'wcpay/types/charges';

export interface ExtendedDispute
	extends Omit< Dispute, 'evidence' | 'charge' > {
	merchant_name?: string;
	merchant_address?: string;
	merchant_email?: string;
	merchant_phone?: string;
	evidence: {
		[ key: string ]:
			| string
			| Record< string, boolean >
			| Record< string, string >;
	};
	charge: Charge;
}

export interface AccountDetails {
	name: string;
	support_address_city: string;
	support_address_country: string;
	support_address_line1: string;
	support_address_line2: string;
	support_address_postal_code: string;
	support_address_state: string;
}

export interface CoverLetterData {
	merchantAddress: string;
	merchantName: string;
	merchantEmail: string;
	merchantPhone: string;
	today: string;
	acquiringBank: string;
	caseNumber: string;
	transactionId: string;
	transactionDate: string;
	customerName: string;
	product: string;
	orderDate: string;
	deliveryDate: string;
	refundStatus?: string;
	duplicateStatus?: string;
}

export interface CoverLetterProps {
	value: string;
	onChange: ( value: string ) => void;
	readOnly?: boolean;
}

export interface RecommendedDocument {
	key: string;
	label: string;
	description: string | null;
	order: number;
}

export interface DocumentField {
	key: string;
	label: string;
	description: string | null;
	fileName?: string;
	fileSize?: number;
	onFileChange: ( key: string, file: File ) => Promise< void >;
	onFileRemove: () => Promise< void >;
	uploaded?: boolean;
	readOnly?: boolean;
	isBusy?: boolean;
}

export interface FileUploadControlProps {
	fileName?: string;
	fileSize?: number;
	description: string | null;
	onFileChange: ( file: File ) => void;
	onFileRemove: () => void;
	disabled?: boolean;
	isDone?: boolean;
	isBusy?: boolean;
	accept?: string;
	label: string;
}

export interface RecommendedDocumentsProps {
	fields: DocumentField[];
	readOnly?: boolean;
}

export interface BaseEvidence {
	product_description: string;
	receipt: string;
	customer_communication: string;
	customer_signature: string;
	refund_policy: string;
	duplicate_charge_documentation: string;
	shipping_documentation: string;
	service_documentation: string;
	cancellation_policy: string;
	cancellation_rebuttal: string;
	access_activity_log: string;
	uncategorized_file: string;
	uncategorized_text: string;
	shipping_carrier: string;
	shipping_date: string;
	shipping_tracking_number: string;
	shipping_address: string;
	customer_purchase_ip: string;
	[ key: string ]: string;
}

export type EvidenceState = Partial< BaseEvidence > & {
	[ key: string ]: string | undefined;
};
