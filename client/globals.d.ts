declare interface RequirementError {
	code: string;
	reason: string;
	requirement: string;
}

declare interface Country {
	key: string;
	name: string;
	types: BusinessType[];
}

declare interface BusinessType {
	key: string;
	name: string;
	description: string;
	structures: BusinessStructure[];
}

declare interface BusinessStructure {
	key: string;
	name: string;
}

declare interface MccsDisplayTreeItem {
	id: string;
	type: string;
	title: string;
	items?: MccsDisplayTreeItem[];
	mcc?: number;
	keywords?: string[];
}

declare const wcpaySettings: {
	connectUrl: string;
	isSubscriptionsActive: boolean;
	featureFlags: {
		customSearch: boolean;
		isAuthAndCaptureEnabled: boolean;
		simplifyDepositsUi?: boolean;
		paymentTimeline: boolean;
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
		deposits?: {
			status: string;
			interval: string;
			weekly_anchor: string;
			monthly_anchor: null | number;
			delay_days: null | number;
			completed_waiting_period: boolean;
			minimum_deposit_amounts: Record< string, number >;
		};
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
	fraudProtection: {
		isWelcomeTourDismissed?: boolean;
	};
	accountDefaultCurrency: string;
	isFraudProtectionSettingsEnabled: boolean;
	frtDiscoverBannerSettings: string;
	onboardingTestMode: boolean;
	onboardingFieldsData?: {
		business_types: Country[];
		mccs_display_tree: MccsDisplayTreeItem[];
	};
};

declare const wcTracks: any;
