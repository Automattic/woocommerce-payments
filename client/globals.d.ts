/**
 * Internal dependencies
 */
import type { MccsDisplayTreeItem, Country } from 'onboarding/types';
import { PaymentMethodToPluginsMap } from './components/duplicate-notice';
import { WCPayExpressCheckoutParams } from './express-checkout/utils';

declare global {
	const wcpaySettings: {
		version: string;
		connectUrl: string;
		overviewUrl: string;
		isSubscriptionsActive: boolean;
		featureFlags: {
			customSearch: boolean;
			woopay: boolean;
			documents: boolean;
			woopayExpressCheckout: boolean;
			isAuthAndCaptureEnabled: boolean;
			paymentTimeline: boolean;
			isDisputeIssuerEvidenceEnabled: boolean;
			isPaymentOverviewWidgetEnabled?: boolean;
			multiCurrency?: boolean;
		};
		accountFees: Record< string, any >;
		fraudServices: unknown[];
		testMode: boolean;
		testModeOnboarding: boolean;
		devMode: boolean;
		isJetpackConnected: boolean;
		isJetpackIdcActive: boolean;
		isAccountConnected: boolean;
		isAccountValid: boolean;
		accountStatus: Partial< {
			email?: string;
			created: string;
			isLive?: boolean;
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
				minimum_manual_deposit_amounts: Record< string, number >;
				minimum_scheduled_deposit_amounts: Record< string, number >;
			};
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
		} >;
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
		siteLogoUrl: string;
		shouldUseExplicitPrice: boolean;
		fraudProtection: {
			isWelcomeTourDismissed?: boolean;
		};
		progressiveOnboarding?: {
			isEnabled: boolean;
			isComplete: boolean;
			isEligibilityModalDismissed: boolean;
		};
		enabledPaymentMethods: string[];
		dismissedDuplicateNotices: PaymentMethodToPluginsMap;
		accountDefaultCurrency: string;
		isFRTReviewFeatureActive: boolean;
		frtDiscoverBannerSettings: string;
		onboardingFieldsData?: {
			business_types: Country[];
			mccs_display_tree: MccsDisplayTreeItem[];
			industry_to_mcc: { [ key: string ]: string };
		};
		storeCurrency: string;
		isMultiCurrencyEnabled: string;
		errorMessage: string;
		onBoardingDisabled: boolean;
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
		isInstantDepositNoticeDismissed: boolean;
		isConnectionSuccessModalDismissed: boolean;
		userLocale: {
			/**
			 * The locale of the current user profile, represented as a locale code supported by transact-platform-server.
			 *
			 * @example 'es' // Spanish
			 *
			 * @see WC_Payments_Utils::convert_locale_to_language_code
			 */
			code: string;
			/**
			 * The English name of the locale.
			 *
			 * @example 'Spanish'
			 */
			english_name: string;
			/**
			 * The native name of the locale.
			 *
			 * @example 'Español'
			 */
			native_name: string;
		};
		trackingInfo?: {
			hosting_provider: string;
		};
		isOverviewSurveySubmitted: boolean;
		lifetimeTPV: number;
		defaultExpressCheckoutBorderRadius: string;
		dateFormat: string;
		timeFormat: string;
	};

	const wc: {
		wcSettings: typeof wcSettingsModule;
		tracks: {
			recordEvent: (
				eventName: string,
				eventProperties: Record< string, unknown >
			) => void;
		};
	};

	const wcTracks: {
		isEnabled: boolean;
		recordEvent: (
			eventName: string,
			eventProperties: Record< string, unknown >
		) => void;
	};

	const wcSettings: {
		admin: {
			onboarding: {
				profile: {
					wccom_connected: boolean;
					industry?: string[];
				};
			};
			currentUserData: {
				first_name: string;
			};
			preloadSettings: {
				general: {
					woocommerce_allowed_countries: string;
					woocommerce_all_except_countries: string[];
					woocommerce_specific_allowed_countries: string[];
					woocommerce_default_country: string;
				};
			};
			siteVisibilitySettings: {
				woocommerce_share_key: string;
				woocommerce_coming_soon: string;
				woocommerce_private_link: string;
			};
			timeZone: string;
		};
		adminUrl: string;
		countries: Record< string, string >;
		homeUrl: string;
		locale: {
			/**
			 * The locale of the current site, as set in WP Admin → Settings → General.
			 *
			 * @example 'en_AU' // English (Australia)
			 */
			siteLocale: string;
			/**
			 * The locale of the current user profile, as set in WP Admin → Users → Profile → Language.
			 *
			 * @example 'en_UK' // English (United Kingdom)
			 */
			userLocale: string;
		};
		siteTitle: string;
	};

	interface WcSettings {
		ece_data?: WCPayExpressCheckoutParams;
		woocommerce_payments_data: typeof wcpaySettings;
	}

	const wcSettingsModule: {
		getSetting: <
			K extends keyof WcSettings,
			T extends WcSettings[ K ] | undefined
		>(
			setting: K,
			fallback?: T
		) => WcSettings[ K ] | T;
	};

	interface Window {
		wcpaySettings: typeof wcpaySettings;
		wc: typeof wc;
		wcTracks: typeof wcTracks;
		wcSettings: typeof wcSettings;
	}
}
