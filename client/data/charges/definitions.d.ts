type BillingDetails = {
	name: string;
};

export type Charge = {
	id: string;
	billing_details: BillingDetails;
};
