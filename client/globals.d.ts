declare const wcpaySettings: {
	connectUrl: string;
	isSubscriptionsActive: boolean;
	featureFlags: {
		customSearch: boolean;
	};
	fraudServices: unknown[];
	isJetpackConnected: boolean;
	isJetpackIdcActive: boolean;
	accountStatus: {
		error?: boolean;
		status?: string;
		hasSubmittedVatData?: boolean;
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
	accountEmail: string;
	currentUserEmail: string;
	zeroDecimalCurrencies: string[];
};

declare const wcTracks: any;
