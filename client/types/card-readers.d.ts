export interface AccountBusinessSupportAddress {
	line1: string;
	line2: string;
	city: string;
	country: string;
	postal_code: string;
	state: string;
}

export interface FetchReceiptPayload {
	accountBusinessSupportAddress: AccountBusinessSupportAddress;
	accountBusinessName: string;
	accountBusinessURL: string;
	accountBusinessSupportEmail: string;
	accountBusinessSupportPhone: string;
}

export interface CardReaderFileUploadProps {
	fieldKey: string;
	label: string;
	accept: string;
	disabled?: boolean;
	help?: string;
	purpose: string;
	fileID: string;
	updateFileID: ( id: string ) => void;
}
