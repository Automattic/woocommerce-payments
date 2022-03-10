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

export interface CardReader {
	id: string;
	livemode: boolean;
	device_type: string;
	label: string;
	location: string;
	metadata: Record< string, any >;
	status: string;
	is_active: boolean;
}
export interface CardReaders {
	readers: CardReader[];
	isLoading: boolean;
}

export interface CardReaderSummary {
	reader_id: string;
	count: number;
	status: string;
	amount: number;
	fee: {
		currency: string;
	};
}

export interface CardReaderStats {
	readers: CardReaderSummary[];
	chargeError: string;
	isLoading: boolean;
}

export interface CardReaderListItem {
	id: string;
	device_type: string;
	is_active: boolean;
}

export interface CardReaderListItemProps {
	reader: CardReaderListItem;
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
