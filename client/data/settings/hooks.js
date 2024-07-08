/** @format */

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';

export const useSavedCards = () => {
	const { updateIsSavedCardsEnabled } = useDispatch( STORE_NAME );

	const isSavedCardsEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsSavedCardsEnabled()
	);

	return [ isSavedCardsEnabled, updateIsSavedCardsEnabled ];
};

export const useCardPresentEligible = () => {
	const { updateIsCardPresentEligible } = useDispatch( STORE_NAME );

	const isCardPresentEligible = useSelect( ( select ) =>
		select( STORE_NAME ).getIsCardPresentEligible()
	);

	return [ isCardPresentEligible, updateIsCardPresentEligible ];
};

export const useEnabledPaymentMethodIds = () => {
	const { updateEnabledPaymentMethodIds } = useDispatch( STORE_NAME );

	const enabledPaymentMethodIds = useSelect( ( select ) =>
		select( STORE_NAME ).getEnabledPaymentMethodIds()
	);

	return [ enabledPaymentMethodIds, updateEnabledPaymentMethodIds ];
};

export const useAccountDomesticCurrency = () =>
	useSelect( ( select ) =>
		select( STORE_NAME ).getAccountDomesticCurrency()
	);

export const useSelectedPaymentMethod = () => {
	const { updateSelectedPaymentMethod } = useDispatch( STORE_NAME );

	const enabledPaymentMethodIds = useSelect( ( select ) =>
		select( STORE_NAME ).getEnabledPaymentMethodIds()
	);

	return [ enabledPaymentMethodIds, updateSelectedPaymentMethod ];
};

export const useUnselectedPaymentMethod = () => {
	const { updateUnselectedPaymentMethod } = useDispatch( STORE_NAME );

	const enabledPaymentMethodIds = useSelect( ( select ) =>
		select( STORE_NAME ).getEnabledPaymentMethodIds()
	);

	return [ enabledPaymentMethodIds, updateUnselectedPaymentMethod ];
};

export const useDebugLog = () => {
	const { updateIsDebugLogEnabled } = useDispatch( STORE_NAME );

	const isDebugLogEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsDebugLogEnabled()
	);

	return [ isDebugLogEnabled, updateIsDebugLogEnabled ];
};

export const useTestMode = () => {
	const { updateIsTestModeEnabled } = useDispatch( STORE_NAME );

	const isTestModeEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsTestModeEnabled()
	);

	return [ isTestModeEnabled, updateIsTestModeEnabled ];
};

export const useDevMode = () =>
	useSelect( ( select ) => select( STORE_NAME ).getIsDevModeEnabled(), [] );

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

export const useAccountStatementDescriptor = () => {
	const { updateAccountStatementDescriptor } = useDispatch( STORE_NAME );

	const accountStatementDescriptor = useSelect( ( select ) =>
		select( STORE_NAME ).getAccountStatementDescriptor()
	);

	return [ accountStatementDescriptor, updateAccountStatementDescriptor ];
};

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

export const useAccountBusinessName = () => {
	const { updateAccountBusinessName } = useDispatch( STORE_NAME );

	const accountBusinessName = useSelect( ( select ) =>
		select( STORE_NAME ).getAccountBusinessName()
	);

	return [ accountBusinessName, updateAccountBusinessName ];
};

export const useAccountBusinessURL = () => {
	const { updateAccountBusinessURL } = useDispatch( STORE_NAME );

	const accountBusinessUrl = useSelect( ( select ) =>
		select( STORE_NAME ).getAccountBusinessURL()
	);

	return [ accountBusinessUrl, updateAccountBusinessURL ];
};

export const useAccountBusinessSupportAddress = () => {
	const { updateAccountBusinessSupportAddress } = useDispatch( STORE_NAME );

	const data = useSelect( ( select ) => {
		const {
			getAccountBusinessSupportAddress,
			getAccountBusinessSupportAddressCountry,
			getAccountBusinessSupportAddressLine1,
			getAccountBusinessSupportAddressLine2,
			getAccountBusinessSupportAddressCity,
			getAccountBusinessSupportAddressState,
			getAccountBusinessSupportAddressPostalCode,
		} = select( STORE_NAME );

		return [
			getAccountBusinessSupportAddress(),
			getAccountBusinessSupportAddressCountry(),
			getAccountBusinessSupportAddressLine1(),
			getAccountBusinessSupportAddressLine2(),
			getAccountBusinessSupportAddressCity(),
			getAccountBusinessSupportAddressState(),
			getAccountBusinessSupportAddressPostalCode(),
		];
	} );

	return [ ...data, updateAccountBusinessSupportAddress ];
};

export const useAccountBusinessSupportEmail = () => {
	const { updateAccountBusinessSupportEmail } = useDispatch( STORE_NAME );

	const accountBusinessSupportEmail = useSelect( ( select ) =>
		select( STORE_NAME ).getAccountBusinessSupportEmail()
	);

	return [ accountBusinessSupportEmail, updateAccountBusinessSupportEmail ];
};

export const useAccountBusinessSupportPhone = () => {
	const { updateAccountBusinessSupportPhone } = useDispatch( STORE_NAME );

	const accountBusinessSupportPhone = useSelect( ( select ) =>
		select( STORE_NAME ).getAccountBusinessSupportPhone()
	);

	return [ accountBusinessSupportPhone, updateAccountBusinessSupportPhone ];
};

export const useAccountBrandingLogo = () => {
	const { updateAccountBrandingLogo } = useDispatch( STORE_NAME );

	const accountBrandingLogo = useSelect( ( select ) =>
		select( STORE_NAME ).getAccountBrandingLogo()
	);

	return [ accountBrandingLogo, updateAccountBrandingLogo ];
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

export const useReportingExportLanguage = () => {
	const { updateExportLanguage } = useDispatch( STORE_NAME );

	const exportLanguage = useSelect( ( select ) =>
		select( STORE_NAME ).getExportLanguage()
	);

	return [ exportLanguage, updateExportLanguage ];
};

export const useDepositDelayDays = () =>
	useSelect( ( select ) => select( STORE_NAME ).getDepositDelayDays(), [] );

export const useCompletedWaitingPeriod = () =>
	useSelect( ( select ) => select( STORE_NAME ).getCompletedWaitingPeriod() );

export const useDepositStatus = () =>
	useSelect( ( select ) => select( STORE_NAME ).getDepositStatus(), [] );

export const useDepositRestrictions = () =>
	useSelect( ( select ) => select( STORE_NAME ).getDepositRestrictions() );

export const useManualCapture = () => {
	const { updateIsManualCaptureEnabled } = useDispatch( STORE_NAME );

	const isManualCaptureEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsManualCaptureEnabled()
	);

	return [ isManualCaptureEnabled, updateIsManualCaptureEnabled ];
};

export const useIsWCPayEnabled = () => {
	const { updateIsWCPayEnabled } = useDispatch( STORE_NAME );

	const IsWCPayEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsWCPayEnabled()
	);

	return [ IsWCPayEnabled, updateIsWCPayEnabled ];
};

export const useGetAvailablePaymentMethodIds = () =>
	useSelect( ( select ) =>
		select( STORE_NAME ).getAvailablePaymentMethodIds()
	);

export const useGetPaymentMethodStatuses = () =>
	useSelect( ( select ) => select( STORE_NAME ).getPaymentMethodStatuses() );

export const useGetDuplicatedPaymentMethodIds = () =>
	useSelect( ( select ) =>
		select( STORE_NAME ).getDuplicatedPaymentMethodIds()
	);

export const useGetSettings = () =>
	useSelect( ( select ) => select( STORE_NAME ).getSettings() );

export const useSettings = () => {
	const { saveSettings } = useDispatch( STORE_NAME );
	const isSaving = useSelect( ( select ) =>
		select( STORE_NAME ).isSavingSettings()
	);

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
	};
};

export const usePaymentRequestEnabledSettings = () => {
	const { updateIsPaymentRequestEnabled } = useDispatch( STORE_NAME );

	const isPaymentRequestEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsPaymentRequestEnabled()
	);

	return [ isPaymentRequestEnabled, updateIsPaymentRequestEnabled ];
};

export const usePaymentRequestLocations = () => {
	const { updatePaymentRequestLocations } = useDispatch( STORE_NAME );

	const paymentRequestLocations = useSelect( ( select ) =>
		select( STORE_NAME ).getPaymentRequestLocations()
	);

	return [ paymentRequestLocations, updatePaymentRequestLocations ];
};

export const usePaymentRequestButtonType = () => {
	const { updatePaymentRequestButtonType } = useDispatch( STORE_NAME );

	const paymentRequestButtonType = useSelect( ( select ) =>
		select( STORE_NAME ).getPaymentRequestButtonType()
	);

	return [ paymentRequestButtonType, updatePaymentRequestButtonType ];
};

export const usePaymentRequestButtonSize = () => {
	const { updatePaymentRequestButtonSize } = useDispatch( STORE_NAME );

	const paymentRequestButtonSize = useSelect( ( select ) =>
		select( STORE_NAME ).getPaymentRequestButtonSize()
	);

	return [ paymentRequestButtonSize, updatePaymentRequestButtonSize ];
};

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

export const useGetSavingError = () => {
	return useSelect( ( select ) => select( STORE_NAME ).getSavingError(), [] );
};

export const useWooPayEnabledSettings = () => {
	const { updateIsWooPayEnabled } = useDispatch( STORE_NAME );

	const isWooPayEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsWooPayEnabled()
	);

	return [ isWooPayEnabled, updateIsWooPayEnabled ];
};

export const useWooPayCustomMessage = () => {
	const { updateWooPayCustomMessage } = useDispatch( STORE_NAME );

	const wooPayCustomMessage = useSelect( ( select ) =>
		select( STORE_NAME ).getWooPayCustomMessage()
	);

	return [ wooPayCustomMessage, updateWooPayCustomMessage ];
};

export const useWooPayStoreLogo = () => {
	const { updateWooPayStoreLogo } = useDispatch( STORE_NAME );

	const wooPayStoreLogo = useSelect( ( select ) =>
		select( STORE_NAME ).getWooPayStoreLogo()
	);

	return [ wooPayStoreLogo, updateWooPayStoreLogo ];
};

export const useWooPayLocations = () => {
	const { updateWooPayLocations } = useDispatch( STORE_NAME );

	const wooPayLocations = useSelect( ( select ) =>
		select( STORE_NAME ).getWooPayLocations()
	);

	return [ wooPayLocations, updateWooPayLocations ];
};

export const useCurrentProtectionLevel = () => {
	const { updateProtectionLevel } = useDispatch( STORE_NAME );

	const currentProtectionLevel = useSelect( ( select ) =>
		select( STORE_NAME ).getCurrentProtectionLevel()
	);

	return [ currentProtectionLevel, updateProtectionLevel ];
};

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

export const useWooPayShowIncompatibilityNotice = () =>
	useSelect( ( select ) =>
		select( STORE_NAME ).getShowWooPayIncompatibilityNotice()
	);

export const useExpressCheckoutShowIncompatibilityNotice = () =>
	useSelect( ( select ) =>
		select( STORE_NAME ).getShowExpressCheckoutIncompatibilityNotice()
	);

export const useStripeBilling = () => {
	const { updateIsStripeBillingEnabled } = useDispatch( STORE_NAME );

	const isStripeBillingEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsStripeBillingEnabled()
	);

	return [ isStripeBillingEnabled, updateIsStripeBillingEnabled ];
};

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
