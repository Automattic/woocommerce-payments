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
