/** @format */

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';

export const useEnabledPaymentMethodIds = () => {
	const { updateEnabledPaymentMethodIds } = useDispatch( STORE_NAME );

	const enabledPaymentMethodIds = useSelect( ( select ) =>
		select( STORE_NAME ).getEnabledPaymentMethodIds()
	);

	return [ enabledPaymentMethodIds, updateEnabledPaymentMethodIds ];
};

export const useSelectedPaymentMethod = () => {
	const { updateSelectedPaymentMethod } = useDispatch( STORE_NAME );

	const enabledPaymentMethodIds = useSelect( ( select ) =>
		select( STORE_NAME ).getEnabledPaymentMethodIds()
	);

	return [ enabledPaymentMethodIds, updateSelectedPaymentMethod ];
};

export const useTestMode = () => {
	const { updateIsTestModeEnabled } = useDispatch( STORE_NAME );

	const isTestModeEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsTestModeEnabled()
	);

	return [ isTestModeEnabled, updateIsTestModeEnabled ];
};

export const useTestModeOnboarding = () =>
	useSelect(
		( select ) => select( STORE_NAME ).getIsTestModeOnboarding(),
		[]
	);

export const useMultiCurrency = () => {
	const { updateIsMultiCurrencyEnabled } = useDispatch( STORE_NAME );

	const isMultiCurrencyEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsMultiCurrencyEnabled()
	);

	return [ isMultiCurrencyEnabled, updateIsMultiCurrencyEnabled ];
};

export const useManualCapture = () => {
	const { updateIsManualCaptureEnabled } = useDispatch( STORE_NAME );

	const isManualCaptureEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsManualCaptureEnabled()
	);

	return [ isManualCaptureEnabled, updateIsManualCaptureEnabled ];
};

export const useGetAvailablePaymentMethodIds = () =>
	useSelect( ( select ) =>
		select( STORE_NAME ).getAvailablePaymentMethodIds()
	);

export const useGetPaymentMethodStatuses = () =>
	useSelect( ( select ) => select( STORE_NAME ).getPaymentMethodStatuses() );

export const useGetSettings = () =>
	useSelect( ( select ) => select( STORE_NAME ).getSettings() );

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

export const usePaymentRequestEnabledSettings = () => {
	const { updateIsPaymentRequestEnabled } = useDispatch( STORE_NAME );

	const isPaymentRequestEnabled = useSelect( ( select ) =>
		select( STORE_NAME ).getIsPaymentRequestEnabled()
	);

	return [ isPaymentRequestEnabled, updateIsPaymentRequestEnabled ];
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
