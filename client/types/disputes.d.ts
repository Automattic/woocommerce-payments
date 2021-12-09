// eslint-disable-next-line wpcalypso/import-docblock
import { Order } from '@woocommerce/api';
import { Charge } from './charges';
import { BalanceTransaction } from './balance-transactions';

interface Evidence {
	[ key: string ]:
		| string
		| Record< string, boolean >
		| Record< string, string >;
}

interface EvidenceDetails {
	has_evidence: boolean;
	due_by: number;
}

export interface Dispute {
	status: string;
	id: string;
	evidence_details?: EvidenceDetails;
	metadata: Record< string, any >;
	productType: string;
	order?: Order;
	evidence: Evidence;
	fileSize?: Record< string, number >;
	reason: string;
	charge: Charge;
	amount: number;
	currency: string;
	created: number;
	balance_transactions: BalanceTransaction[];
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
