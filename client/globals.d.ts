declare const wcpaySettings: {
	connectUrl: string;
	isSubscriptionsActive: boolean;
	featureFlags: {
		customSearch: boolean;
		customDepositSchedules: boolean;
	};
	fraudServices: unknown[];
	isJetpackConnected: boolean;
	isJetpackIdcActive: boolean;
	accountStatus: {
		error?: boolean;
		deposits?: {
			completed_waiting_period: boolean;
			delay_days: number;
			interval: 'daily' | 'weekly' | 'monthly' | 'manual';
			minimum_deposit_amounts: Record< string, number >;
			monthly_anchor: number | null;
			status: string;
			weekly_anchor: string;
		};
		status?: string;
		country?: string;
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
	restUrl: string;
	shouldUseExplicitPrice: boolean;
	numDisputesNeedingResponse: string;
};

declare const wcTracks: any;
