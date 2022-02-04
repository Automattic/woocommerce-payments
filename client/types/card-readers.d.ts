export interface FetchReceiptPayload {
	accountBusinessSupportAddress: {
		line1: string;
		line2: string;
		city: string;
		country: string;
		postal_code: string;
		state: string;
	};
	accountBusinessName: string;
	accountBusinessURL: string;
	accountBusinessSupportEmail: string;
	accountBusinessSupportPhone: string;
}
