declare const wcpaySettings: {
	connectUrl: string;
	isSubscriptionsActive: boolean;
	featureFlags: {
		customSearch: boolean;
		capital: boolean;
	};
	fraudServices: unknown[];
	isJetpackConnected: boolean;
	isJetpackIdcActive: boolean;
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
		availableStates: Array< Record< string, string > >;
	};
};
