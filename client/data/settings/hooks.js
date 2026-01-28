/* eslint-disable valid-jsdoc,jsdoc/require-returns-description */
// disabled while we work on getting all the hooks typed.

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<boolean>}
 */
export const useSavedCards = () => {
	const { updateIsSavedCardsEnabled } = useDispatch( STORE_NAME );

	const isSavedCardsEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsSavedCardsEnabled()
	);

	return [ isSavedCardsEnabled, updateIsSavedCardsEnabled ];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<boolean>}
 */
export const useCardPresentEligible = () => {
	const { updateIsCardPresentEligible } = useDispatch( STORE_NAME );

	const isCardPresentEligible = useSelect( ( select ) =>
		select( STORE_NAME ).getIsCardPresentEligible()
	);

	return [ isCardPresentEligible, updateIsCardPresentEligible ];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<string[]>}
 */
export const useEnabledPaymentMethodIds = () => {
	const { updateEnabledPaymentMethodIds } = useDispatch( STORE_NAME );

	const enabledPaymentMethodIds = useSelect( ( select ) =>
		select( STORE_NAME ).getEnabledPaymentMethodIds()
	);

	return [ enabledPaymentMethodIds, updateEnabledPaymentMethodIds ];
};

/**
 * @return {string}
 */
export const useAccountDomesticCurrency = () =>
	useSelect( ( select ) =>
		select( STORE_NAME ).getAccountDomesticCurrency()
	);

/**
 * @return {Array<Array<string>, function(string): void>}
 */
export const useSelectedPaymentMethod = () => {
	const { updateSelectedPaymentMethod } = useDispatch( STORE_NAME );

	const enabledPaymentMethodIds = useSelect( ( select ) =>
		select( STORE_NAME ).getEnabledPaymentMethodIds()
	);

	return [ enabledPaymentMethodIds, updateSelectedPaymentMethod ];
};

/**
 * @return {Array<Array<string>, function(string): void>}
 */
export const useUnselectedPaymentMethod = () => {
	const { updateUnselectedPaymentMethod } = useDispatch( STORE_NAME );

	const enabledPaymentMethodIds = useSelect( ( select ) =>
		select( STORE_NAME ).getEnabledPaymentMethodIds()
	);

	return [ enabledPaymentMethodIds, updateUnselectedPaymentMethod ];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<boolean>}
 */
export const useDebugLog = () => {
	const { updateIsDebugLogEnabled } = useDispatch( STORE_NAME );

	const isDebugLogEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsDebugLogEnabled()
	);

	return [ isDebugLogEnabled, updateIsDebugLogEnabled ];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<boolean>}
 */
export const useTestMode = () => {
	const { updateIsTestModeEnabled } = useDispatch( STORE_NAME );

	const isTestModeEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsTestModeEnabled()
	);

	return [ isTestModeEnabled, updateIsTestModeEnabled ];
};

/**
 * @return {boolean}
 */
export const useTestModeOnboarding = () =>
	useSelect(
		( select ) => select( STORE_NAME ).getIsTestModeOnboarding(),
		[]
	);

/**
 * @return {boolean}
 */
export const useDevMode = () =>
	useSelect( ( select ) => select( STORE_NAME ).getIsDevModeEnabled(), [] );

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<boolean>}
 */
export const useMultiCurrency = () => {
	const { updateIsMultiCurrencyEnabled } = useDispatch( STORE_NAME );

	const isMultiCurrencyEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsMultiCurrencyEnabled()
	);

	return [ isMultiCurrencyEnabled, updateIsMultiCurrencyEnabled ];
};

export const useWCPaySubscriptions = () => {
	const { updateIsWCPaySubscriptionsEnabled } = useDispatch( STORE_NAME );

	const isWCPaySubscriptionsEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsWCPaySubscriptionsEnabled()
	);
	const isWCPaySubscriptionsEligible = useSelect( ( select ) =>
		select( STORE_NAME ).getIsWCPaySubscriptionsEligible()
	);

	return [
		isWCPaySubscriptionsEnabled,
		isWCPaySubscriptionsEligible,
		updateIsWCPaySubscriptionsEnabled,
	];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<string>}
 */
export const useAccountStatementDescriptor = () => {
	const { updateAccountStatementDescriptor } = useDispatch( STORE_NAME );

	const accountStatementDescriptor = useSelect( ( select ) =>
		select( STORE_NAME ).getAccountStatementDescriptor()
	);

	return [ accountStatementDescriptor, updateAccountStatementDescriptor ];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<string>}
 */
export const useAccountStatementDescriptorKanji = () => {
	const { updateAccountStatementDescriptorKanji } = useDispatch( STORE_NAME );

	const accountStatementDescriptorKanji = useSelect( ( select ) =>
		select( STORE_NAME ).getAccountStatementDescriptorKanji()
	);

	return [
		accountStatementDescriptorKanji,
		updateAccountStatementDescriptorKanji,
	];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<string>}
 */
export const useAccountStatementDescriptorKana = () => {
	const { updateAccountStatementDescriptorKana } = useDispatch( STORE_NAME );

	const accountStatementDescriptorKana = useSelect( ( select ) =>
		select( STORE_NAME ).getAccountStatementDescriptorKana()
	);

	return [
		accountStatementDescriptorKana,
		updateAccountStatementDescriptorKana,
	];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<string>}
 */
export const useAccountBusinessSupportEmail = () => {
	const { updateAccountBusinessSupportEmail } = useDispatch( STORE_NAME );

	const accountBusinessSupportEmail = useSelect( ( select ) =>
		select( STORE_NAME ).getAccountBusinessSupportEmail()
	);

	return [ accountBusinessSupportEmail, updateAccountBusinessSupportEmail ];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<string>}
 */
export const useAccountBusinessSupportPhone = () => {
	const { updateAccountBusinessSupportPhone } = useDispatch( STORE_NAME );

	const accountBusinessSupportPhone = useSelect( ( select ) =>
		select( STORE_NAME ).getAccountBusinessSupportPhone()
	);

	return [ accountBusinessSupportPhone, updateAccountBusinessSupportPhone ];
};

export const useDepositScheduleInterval = () => {
	const { updateDepositScheduleInterval } = useDispatch( STORE_NAME );

	const depositScheduleInterval = useSelect( ( select ) =>
		select( STORE_NAME ).getDepositScheduleInterval()
	);

	return [ depositScheduleInterval, updateDepositScheduleInterval ];
};

export const useDepositScheduleWeeklyAnchor = () => {
	const { updateDepositScheduleWeeklyAnchor } = useDispatch( STORE_NAME );

	const depositScheduleWeeklyAnchor = useSelect( ( select ) =>
		select( STORE_NAME ).getDepositScheduleWeeklyAnchor()
	);

	return [ depositScheduleWeeklyAnchor, updateDepositScheduleWeeklyAnchor ];
};

export const useDepositScheduleMonthlyAnchor = () => {
	const { updateDepositScheduleMonthlyAnchor } = useDispatch( STORE_NAME );

	const depositScheduleMonthlyAnchor = useSelect( ( select ) =>
		select( STORE_NAME ).getDepositScheduleMonthlyAnchor()
	);

	return [ depositScheduleMonthlyAnchor, updateDepositScheduleMonthlyAnchor ];
};

export const useDepositDelayDays = () =>
	useSelect( ( select ) => select( STORE_NAME ).getDepositDelayDays(), [] );

export const useCompletedWaitingPeriod = () =>
	useSelect( ( select ) => select( STORE_NAME ).getCompletedWaitingPeriod() );

/**
 * @return {string}
 */
export const useDepositStatus = () =>
	useSelect( ( select ) => select( STORE_NAME ).getDepositStatus(), [] );

/**
 * @return {string}
 */
export const useDepositRestrictions = () =>
	useSelect( ( select ) => select( STORE_NAME ).getDepositRestrictions() );

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<boolean>}
 */
export const useManualCapture = () => {
	const { updateIsManualCaptureEnabled } = useDispatch( STORE_NAME );

	const isManualCaptureEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsManualCaptureEnabled()
	);

	return [ isManualCaptureEnabled, updateIsManualCaptureEnabled ];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<boolean>}
 */
export const useIsWCPayEnabled = () => {
	const { updateIsWCPayEnabled } = useDispatch( STORE_NAME );

	const IsWCPayEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsWCPayEnabled()
	);

	return [ IsWCPayEnabled, updateIsWCPayEnabled ];
};

/**
 * @return {string[]} Array of available payment method IDs.
 */
export const useGetAvailablePaymentMethodIds = () =>
	useSelect( ( select ) =>
		select( STORE_NAME ).getAvailablePaymentMethodIds()
	);

/**
 * @return {Record<string, {status: string, requirements: string[]}>}
 */
export const useGetPaymentMethodStatuses = () =>
	useSelect( ( select ) => select( STORE_NAME ).getPaymentMethodStatuses() );

/**
 * @return {string[]}
 */
export const useGetDuplicatedPaymentMethodIds = () =>
	useSelect( ( select ) =>
		select( STORE_NAME ).getDuplicatedPaymentMethodIds()
	);

/**
 * @return {Record<string, any>}
 */
export const useGetSettings = () =>
	useSelect( ( select ) => select( STORE_NAME ).getSettings() );

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').SettingsState}
 */
export const useSettings = () => {
	const { saveSettings } = useDispatch( STORE_NAME );
	const isSaving = useSelect( ( select ) =>
		select( STORE_NAME ).isSavingSettings()
	);
	const isDirty = useSelect( ( select ) => select( STORE_NAME ).isDirty() );

	const isLoading = useSelect( ( select ) => {
		select( STORE_NAME ).getSettings();
		const isResolving = select( STORE_NAME ).isResolving( 'getSettings' );
		const hasFinishedResolving = select( STORE_NAME ).hasFinishedResolution(
			'getSettings'
		);
		return isResolving || ! hasFinishedResolving;
	} );

	return {
		isLoading,
		saveSettings,
		isSaving,
		isDirty,
	};
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<boolean>}
 */
export const usePaymentRequestEnabledSettings = () => {
	const { updateIsPaymentRequestEnabled } = useDispatch( STORE_NAME );

	const isPaymentRequestEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsPaymentRequestEnabled()
	);

	return [ isPaymentRequestEnabled, updateIsPaymentRequestEnabled ];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<boolean>}
 */
export const useAppleGooglePayInPaymentMethodsOptionsEnabledSettings = () => {
	const {
		updateIsAppleGooglePayInPaymentMethodsOptionsEnabled,
	} = useDispatch( STORE_NAME );

	const isAppleGooglePayInPaymentMethodsOptionsEnabled = useSelect(
		( select ) =>
			select(
				STORE_NAME
			).getIsAppleGooglePayInPaymentMethodsOptionsEnabled()
	);

	return [
		isAppleGooglePayInPaymentMethodsOptionsEnabled,
		updateIsAppleGooglePayInPaymentMethodsOptionsEnabled,
	];
};

/**
 * Factory function to create a hook for managing express checkout method locations.
 *
 * @param {string} methodId The method identifier (e.g., 'payment_request', 'woopay').
 * @return {function(): import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<string[]>}
 */
const makeExpressCheckoutLocationHook = ( methodId ) => () => {
	const {
		updateExpressCheckoutProductMethods,
		updateExpressCheckoutCartMethods,
		updateExpressCheckoutCheckoutMethods,
	} = useDispatch( STORE_NAME );

	const productMethods = useSelect( ( select ) =>
		select( STORE_NAME ).getExpressCheckoutProductMethods()
	);
	const cartMethods = useSelect( ( select ) =>
		select( STORE_NAME ).getExpressCheckoutCartMethods()
	);
	const checkoutMethods = useSelect( ( select ) =>
		select( STORE_NAME ).getExpressCheckoutCheckoutMethods()
	);

	const methodsListMap = {
		product: productMethods,
		cart: cartMethods,
		checkout: checkoutMethods,
	};
	const methodsUpdatersMap = {
		product: updateExpressCheckoutProductMethods,
		cart: updateExpressCheckoutCartMethods,
		checkout: updateExpressCheckoutCheckoutMethods,
	};

	const enabledLocations = [
		productMethods.includes( methodId ) && 'product',
		cartMethods.includes( methodId ) && 'cart',
		checkoutMethods.includes( methodId ) && 'checkout',
	].filter( Boolean );

	const locationUpdater = ( location, isChecked ) => {
		methodsUpdatersMap[ location ](
			isChecked
				? [ ...methodsListMap[ location ], methodId ]
				: methodsListMap[ location ].filter(
						( method ) => method !== methodId
				  )
		);
	};

	return [ enabledLocations, locationUpdater ];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<string[]>}
 */
export const usePaymentRequestLocations = makeExpressCheckoutLocationHook(
	'payment_request'
);

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<string>}
 */
export const usePaymentRequestButtonType = () => {
	const { updatePaymentRequestButtonType } = useDispatch( STORE_NAME );

	const paymentRequestButtonType = useSelect( ( select ) =>
		select( STORE_NAME ).getPaymentRequestButtonType()
	);

	return [ paymentRequestButtonType, updatePaymentRequestButtonType ];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<string>}
 */
export const usePaymentRequestButtonSize = () => {
	const { updatePaymentRequestButtonSize } = useDispatch( STORE_NAME );

	const paymentRequestButtonSize = useSelect( ( select ) =>
		select( STORE_NAME ).getPaymentRequestButtonSize()
	);

	return [ paymentRequestButtonSize, updatePaymentRequestButtonSize ];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<string>}
 */
export const usePaymentRequestButtonTheme = () => {
	const { updatePaymentRequestButtonTheme } = useDispatch( STORE_NAME );

	const paymentRequestButtonTheme = useSelect( ( select ) =>
		select( STORE_NAME ).getPaymentRequestButtonTheme()
	);

	return [ paymentRequestButtonTheme, updatePaymentRequestButtonTheme ];
};

export const usePaymentRequestButtonBorderRadius = () => {
	const { updatePaymentRequestButtonBorderRadius } = useDispatch(
		STORE_NAME
	);

	const paymentRequestButtonBorderRadius = useSelect( ( select ) =>
		select( STORE_NAME ).getPaymentRequestButtonBorderRadius()
	);

	return [
		paymentRequestButtonBorderRadius,
		updatePaymentRequestButtonBorderRadius,
	];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').SavingError | null}
 */
export const useGetSavingError = () => {
	return useSelect( ( select ) => select( STORE_NAME ).getSavingError(), [] );
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<boolean>}
 */
export const useWooPayEnabledSettings = () => {
	const { updateIsWooPayEnabled } = useDispatch( STORE_NAME );

	const isWooPayEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsWooPayEnabled()
	);

	return [ isWooPayEnabled, updateIsWooPayEnabled ];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<boolean>}
 */
export const useWooPayGlobalThemeSupportEnabledSettings = () => {
	const { updateIsWooPayGlobalThemeSupportEnabled } = useDispatch(
		STORE_NAME
	);

	const isWooPayGlobalThemeSupportEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsWooPayGlobalThemeSupportEnabled()
	);

	return [
		isWooPayGlobalThemeSupportEnabled,
		updateIsWooPayGlobalThemeSupportEnabled,
	];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<string>}
 */
export const useWooPayCustomMessage = () => {
	const { updateWooPayCustomMessage } = useDispatch( STORE_NAME );

	const wooPayCustomMessage = useSelect( ( select ) =>
		select( STORE_NAME ).getWooPayCustomMessage()
	);

	return [ wooPayCustomMessage, updateWooPayCustomMessage ];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<string>}
 */
export const useWooPayStoreLogo = () => {
	const { updateWooPayStoreLogo } = useDispatch( STORE_NAME );

	const wooPayStoreLogo = useSelect( ( select ) =>
		select( STORE_NAME ).getWooPayStoreLogo()
	);

	return [ wooPayStoreLogo, updateWooPayStoreLogo ];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<string[]>}
 */
export const useWooPayLocations = makeExpressCheckoutLocationHook( 'woopay' );

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<boolean>}
 */
export const useAmazonPayEnabledSettings = () => {
	const { updateIsAmazonPayEnabled } = useDispatch( STORE_NAME );

	const isAmazonPayEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsAmazonPayEnabled()
	);

	return [ isAmazonPayEnabled, updateIsAmazonPayEnabled ];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<string[]>}
 */
export const useAmazonPayLocations = makeExpressCheckoutLocationHook(
	'amazon_pay'
);

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<string>}
 */
export const useCurrentProtectionLevel = () => {
	const { updateProtectionLevel } = useDispatch( STORE_NAME );

	const currentProtectionLevel = useSelect( ( select ) =>
		select( STORE_NAME ).getCurrentProtectionLevel()
	);

	return [ currentProtectionLevel, updateProtectionLevel ];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').AdvancedFraudPreventionSettingsState}
 */
export const useAdvancedFraudProtectionSettings = () => {
	const { updateAdvancedFraudProtectionSettings } = useDispatch( STORE_NAME );

	const advancedFraudProtectionSettings = useSelect( ( select ) =>
		select( STORE_NAME ).getAdvancedFraudProtectionSettings()
	);

	return [
		advancedFraudProtectionSettings,
		updateAdvancedFraudProtectionSettings,
	];
};

/**
 * @return {boolean}
 */
export const useWooPayShowIncompatibilityNotice = () =>
	useSelect( ( select ) =>
		select( STORE_NAME ).getShowWooPayIncompatibilityNotice()
	);

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<boolean>}
 */
export const useStripeBilling = () => {
	const { updateIsStripeBillingEnabled } = useDispatch( STORE_NAME );

	const isStripeBillingEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsStripeBillingEnabled()
	);

	return [ isStripeBillingEnabled, updateIsStripeBillingEnabled ];
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').StripeBillingMigrationState}
 */
export const useStripeBillingMigration = () => {
	const { submitStripeBillingSubscriptionMigration } = useDispatch(
		STORE_NAME
	);

	return useSelect( ( select ) => {
		const { getStripeBillingSubscriptionCount } = select( STORE_NAME );
		const { getIsStripeBillingMigrationInProgress } = select( STORE_NAME );
		const { isResolving } = select( STORE_NAME );
		const hasResolved = select( STORE_NAME ).hasFinishedResolution(
			'scheduleStripeBillingMigration'
		);
		const { getStripeBillingMigratedCount } = select( STORE_NAME );

		return [
			getIsStripeBillingMigrationInProgress(),
			getStripeBillingMigratedCount(),
			getStripeBillingSubscriptionCount(),
			submitStripeBillingSubscriptionMigration,
			isResolving( 'scheduleStripeBillingMigration' ),
			hasResolved,
		];
	}, [] );
};

/**
 * @return {import('wcpay/types/wcpay-data-settings-hooks').GenericSettingsHook<string>}
 */
export const useAccountCommunicationsEmail = () => {
	const { updateAccountCommunicationsEmail } = useDispatch( STORE_NAME );

	const accountCommunicationsEmail = useSelect( ( select ) =>
		select( STORE_NAME ).getAccountCommunicationsEmail()
	);

	return [ accountCommunicationsEmail, updateAccountCommunicationsEmail ];
};
