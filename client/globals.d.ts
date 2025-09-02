/**
 * Internal dependencies
 */
import type { MccsDisplayTreeItem, Country } from 'onboarding/types';
import { PaymentMethodToPluginsMap } from './components/duplicate-notice';
import { WCPayExpressCheckoutParams } from './express-checkout/utils';

declare global {
	interface TosSettingsStripeConnected {
		is_existing_stripe_account: boolean;
	}

	// eslint-disable-next-line @typescript-eslint/naming-convention
	const wcpay_tos_settings: {
		settingsUrl: string;
		tosAgreementDeclined: '' | '1';
		tosAgreementRequired: '' | '1';
		trackStripeConnected: TosSettingsStripeConnected | '';
	};

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
			paymentTimeline: boolean;
			isDisputeIssuerEvidenceEnabled: boolean;
			multiCurrency?: boolean;
			isFRTReviewFeatureActive: boolean;
			isDynamicCheckoutPlaceOrderButtonEnabled: boolean;
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
			testDrive?: boolean;
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
			isDocumentsEnabled?: boolean;
			requirements?: {
				errors?: {
					code: string;
					reason: string;
					requirement: string;
				}[];
			};
			fraudProtection: {
				declineOnAVSFailure: boolean;
				declineOnCVCFailure: boolean;
			};
			/**
			 * Campaigns are temporary flags that are used to enable/disable features for a limited time.
			 */
			campaigns: {
				/**
				 * The flag for the WordPress.org merchant review campaign in 2025.
				 * Eligibility is determined per-account on transact-platform-server.
				 */
				wporgReview2025: boolean;
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
		dismissedDuplicateNotices: PaymentMethodToPluginsMap;
		accountDefaultCurrency: string;
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
		storeName: string;
		isNextDepositNoticeDismissed: boolean;
		isInstantDepositNoticeDismissed: boolean;
		isConnectionSuccessModalDismissed: boolean;
		trackingInfo?: {
			hosting_provider: string;
		};
		isOverviewSurveySubmitted: boolean;
		lifetimeTPV: number;
		defaultExpressCheckoutBorderRadius: string;
		dateFormat: string;
		timeFormat: string;
	};

	const wooPaymentsPaymentMethodDefinitions: Record<
		string,
		PaymentMethodServerDefinition
	>;

	const wooPaymentsPaymentMethodsConfig: Record<
		string,
		{
			isReusable: boolean;
			isBnpl: boolean;
			title: string;
			icon: string;
			darkIcon: string;
			showSaveOption: boolean;
			countries: string[];
			testingInstructions: string;
			forceNetworkSavedCards: boolean;
		}
	>;

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
					woocommerce_store_address: string;
					woocommerce_store_address_2: string;
					woocommerce_store_city: string;
					woocommerce_store_postcode: string;
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
		wcVersion: string;
	};

	const wcpayPluginSettings: {
		exitSurveyLastShown: string | null;
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
		wcpayPluginSettings?: typeof wcpayPluginSettings;
		wooPaymentsPaymentMethodsConfig?: typeof wooPaymentsPaymentMethodsConfig;
	}
}
