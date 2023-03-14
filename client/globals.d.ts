declare interface RequirementError {
	code: string;
	reason: string;
	requirement: string;
}

declare const wcpaySettings: {
	connectUrl: string;
	isSubscriptionsActive: boolean;
	featureFlags: {
		customSearch: boolean;
		customDepositSchedules: boolean;
		isAuthAndCaptureEnabled: boolean;
		simplifyDepositsUi?: boolean;
	};
	fraudServices: unknown[];
	isJetpackConnected: boolean;
	isJetpackIdcActive: boolean;
	accountStatus: {
		email?: string;
		created: string;
		error?: boolean;
		status?: string;
		country?: string;
		paymentsEnabled?: boolean;
		deposits?: Array< any >;
		depositsStatus?: string;
		currentDeadline?: bigint;
		pastDue?: boolean;
		accountLink: string;
		hasSubmittedVatData?: boolean;
		requirements?: {
			errors?: Array< RequirementError >;
		};
		progressiveOnboarding: {
			isEnabled: boolean;
			isComplete: boolean;
			tpv: number;
			firstTransactionDate?: string;
		};
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
	restUrl: string;
	shouldUseExplicitPrice: boolean;
	numDisputesNeedingResponse: string;
};

declare const wcTracks: any;
