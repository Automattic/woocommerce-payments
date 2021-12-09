// eslint-disable-next-line wpcalypso/import-docblock
import { Order } from '@woocommerce/api';
import { Charge } from './charges';
import { BalanceTransaction } from './balance_transactions';

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
	reason: string;
	charge: Charge;
	amount: number;
	currency: string;
	created: number;
	balance_transactions: BalanceTransaction[];
}
