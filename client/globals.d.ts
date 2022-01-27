declare const wcpaySettings: {
	connectUrl: string;
	isSubscriptionsActive: boolean;
	featureFlags: {
		customSearch: boolean;
	};
	fraudServices: unknown[];
	isJetpackConnected: boolean;
	accountStatus: {
		error?: boolean;
		status?: string;
	};
	accountLoans: {
		has_active_loan: boolean;
		has_past_loans: boolean;
		loans: Array< string >;
	};
	connect: {
		country: string;
	};
};
