/**
 * Internal dependencies
 */
import type {
	MccsDisplayTreeItem,
	Country,
	OnboardingFields,
} from 'onboarding/types';

declare global {
	const wcpaySettings: {
		connectUrl: string;
		isSubscriptionsActive: boolean;
		featureFlags: {
			customSearch: boolean;
			isAuthAndCaptureEnabled: boolean;
			paymentTimeline: boolean;
			isDisputeIssuerEvidenceEnabled: boolean;
		};
		fraudServices: unknown[];
		testMode: boolean;
		devMode: boolean;
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
				restrictions:
					| 'deposits_unrestricted'
					| 'deposits_blocked'
					| 'schedule_restricted';
				interval: string;
				weekly_anchor: string;
				monthly_anchor: null | number;
				delay_days: null | number;
				completed_waiting_period: boolean;
				minimum_deposit_amounts: Record< string, number >;
			};
			depositsStatus?: string;
			currentDeadline?: bigint;
			detailsSubmitted?: boolean;
			pastDue?: boolean;
			accountLink: string;
			hasSubmittedVatData?: boolean;
			requirements?: {
				errors?: {
					code: string;
					reason: string;
					requirement: string;
				}[];
			};
			progressiveOnboarding: {
				isEnabled: boolean;
				isComplete: boolean;
				tpv: number;
				firstTransactionDate?: string;
			};
			fraudProtection: {
				declineOnAVSFailure: boolean;
				declineOnCVCFailure: boolean;
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
			availableCountries: Record< string, string >;
		};
		accountEmail: string;
		currentUserEmail: string;
		zeroDecimalCurrencies: string[];
		restUrl: string;
		shouldUseExplicitPrice: boolean;
		fraudProtection: {
			isWelcomeTourDismissed?: boolean;
		};
		progressiveOnboarding?: {
			isNewFlowEnabled: boolean;
			isEnabled: boolean;
			isComplete: boolean;
			isEligibilityModalDismissed: boolean;
		};
		enabledPaymentMethods: string[];
		accountDefaultCurrency: string;
		isFRTReviewFeatureActive: boolean;
		frtDiscoverBannerSettings: string;
		onboardingTestMode: boolean;
		onboardingFieldsData?: {
			business_types: Country[];
			mccs_display_tree: MccsDisplayTreeItem[];
		};
		onboardingFlowState?: {
			current_step: string;
			data: OnboardingFields;
		};
		storeCurrency: string;
		isMultiCurrencyEnabled: string;
		errorMessage: string;
		onBoardingDisabled: boolean;
		isBnplAffirmAfterpayEnabled: boolean;
		connectIncentive?: {
			id: string;
			description: string;
			tc_url: string;
			task_header_content?: string;
			task_badge?: string;
		};
		isWooPayStoreCountryAvailable: boolean;
		isSubscriptionsPluginActive: boolean;
		isStripeBillingEligible: boolean;
		capabilityRequestNotices: Record< string, boolean >;
		storeName: string;
		isNextDepositNoticeDismissed: boolean;
	};

	const wcTracks: any;

	const wcSettings: Record< string, any >;
}
