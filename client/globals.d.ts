/**
 * Internal dependencies
 */
import { MccsDisplayTreeItem, Country } from 'onboarding-prototype/types';

declare global {
	const wcpaySettings: {
		connectUrl: string;
		isSubscriptionsActive: boolean;
		featureFlags: {
			customSearch: boolean;
			isAuthAndCaptureEnabled: boolean;
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
		numDisputesNeedingResponse: string;
		fraudProtection: {
			isWelcomeTourDismissed?: boolean;
		};
		accountDefaultCurrency: string;
		isFRTReviewFeatureActive: boolean;
		frtDiscoverBannerSettings: string;
		onboardingTestMode: boolean;
		onboardingFieldsData?: {
			business_types: Country[];
			mccs_display_tree: MccsDisplayTreeItem[];
		};
		storeCurrency: string;
		isMultiCurrencyEnabled: string;
		errorMessage: string;
		onBoardingDisabled: boolean;
	};

	const wcTracks: any;

	const wcSettings: Record< string, any >;
}
