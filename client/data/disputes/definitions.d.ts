// eslint-disable-next-line wpcalypso/import-docblock
import { Order } from '@woocommerce/api';

export type Evidence = {
	[ key: string ]:
		| string
		| Record< string, boolean >
		| Record< string, string >;
	isUploading: Record< string, boolean >;
	metadata: Record< string, string >;
	uploadingErrors: Record< string, string >;
};

export type Dispute = {
	status: string;
	id: string;
	evidence_details?: {
		has_evidence: boolean;
	};
	metadata: Record< string, string >;
	productType: string;
	order?: Order;
	evidence: Evidence;
	fileSize?: Record< string, number >;
	reason: string;
};
