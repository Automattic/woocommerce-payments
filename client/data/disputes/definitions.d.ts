// eslint-disable-next-line wpcalypso/import-docblock
import { Order } from '@woocommerce/api';
import { Charge } from 'wcpay/data/charges/definitions';

type Evidence = {
	[ key: string ]:
		| string
		| Record< string, boolean >
		| Record< string, string >;
	isUploading: Record< string, boolean >;
	metadata: Record< string, string >;
	uploadingErrors: Record< string, string >;
};

type EvidenceDetails = {
	has_evidence: boolean;
	due_by: number;
};

export type Dispute = {
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
};
