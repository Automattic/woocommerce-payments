interface BillingDetails {
	name: string;
}

export interface Charge {
	id: string;
	billing_details: BillingDetails;
}
