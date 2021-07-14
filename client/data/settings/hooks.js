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

	const enabledPaymentMethodIds = useSelect( ( select ) => {
		const { getEnabledPaymentMethodIds } = select( STORE_NAME );

		return getEnabledPaymentMethodIds();
	} );

	return [ enabledPaymentMethodIds, updateEnabledPaymentMethodIds ];
};

export const useDebugLog = () => {
	const { updateIsDebugLogEnabled } = useDispatch( STORE_NAME );

	const isDebugLogEnabled = useSelect( ( select ) => {
		const { getIsDebugLogEnabled } = select( STORE_NAME );

		return getIsDebugLogEnabled();
	} );

	return [ isDebugLogEnabled, updateIsDebugLogEnabled ];
};

export const useTestMode = () => {
	const { updateIsTestModeEnabled } = useDispatch( STORE_NAME );

	const isTestModeEnabled = useSelect( ( select ) => {
		const { getIsTestModeEnabled } = select( STORE_NAME );

		return getIsTestModeEnabled();
	} );

	return [ isTestModeEnabled, updateIsTestModeEnabled ];
};

export const useDevMode = () => {
	return useSelect( ( select ) => {
		const { getIsDevModeEnabled } = select( STORE_NAME );

		return getIsDevModeEnabled();
	} );
};

export const useAccountStatementDescriptor = () => {
	const { updateAccountStatementDescriptor } = useDispatch( STORE_NAME );

	const accountStatementDescriptor = useSelect( ( select ) => {
		const { getAccountStatementDescriptor } = select( STORE_NAME );

		return getAccountStatementDescriptor();
	} );

	return [ accountStatementDescriptor, updateAccountStatementDescriptor ];
};

export const useManualCapture = () => {
	const { updateIsManualCaptureEnabled } = useDispatch( STORE_NAME );

	const isManualCaptureEnabled = useSelect( ( select ) => {
		const { getIsManualCaptureEnabled } = select( STORE_NAME );

		return getIsManualCaptureEnabled();
	} );

	return [ isManualCaptureEnabled, updateIsManualCaptureEnabled ];
};

export const useIsWCPayEnabled = () => {
	const { updateIsWCPayEnabled } = useDispatch( STORE_NAME );

	const isWCPayEnabled = useSelect( ( select ) => {
		const { getIsWCPayEnabled } = select( STORE_NAME );

		return getIsWCPayEnabled();
	} );

	return [ isWCPayEnabled, updateIsWCPayEnabled ];
};

export const useGetAvailablePaymentMethodIds = () =>
	useSelect( ( select ) => {
		const { getAvailablePaymentMethodIds } = select( STORE_NAME );

		return getAvailablePaymentMethodIds();
	} );

export const useSettings = () => {
	const { saveSettings } = useDispatch( STORE_NAME );
	const isSaving = useSelect( ( select ) =>
		select( STORE_NAME ).isSavingSettings()
	);

	const isLoading = useSelect( ( select ) => {
		select( STORE_NAME ).getSettings();
		const { hasFinishedResolution } = select( STORE_NAME );
		const { isResolving } = select( STORE_NAME );
		const isResolvingSettings = isResolving( 'getSettings' );
		const hasFinishedResolvingSettings = hasFinishedResolution(
			'getSettings'
		);
		return isResolvingSettings || ! hasFinishedResolvingSettings;
	} );

	return {
		isLoading,
		saveSettings,
		isSaving,
	};
};

export const usePaymentRequestEnabledSettings = () => {
	const { updateIsPaymentRequestEnabled } = useDispatch( STORE_NAME );

	const isPaymentRequestEnabled = useSelect( ( select ) => {
		const { getIsPaymentRequestEnabled } = select( STORE_NAME );

		return getIsPaymentRequestEnabled();
	} );

	return [ isPaymentRequestEnabled, updateIsPaymentRequestEnabled ];
};

export const usePaymentRequestLocations = () => {
	const { updatePaymentRequestLocations } = useDispatch( STORE_NAME );

	const paymentRequestLocations = useSelect( ( select ) => {
		const { getPaymentRequestLocations } = select( STORE_NAME );

		return getPaymentRequestLocations();
	} );

	return [ paymentRequestLocations, updatePaymentRequestLocations ];
};

export const usePaymentRequestButtonType = () => {
	const { updatePaymentRequestButtonType } = useDispatch( STORE_NAME );

	const paymentRequestButtonType = useSelect( ( select ) => {
		const { getPaymentRequestButtonType } = select( STORE_NAME );

		return getPaymentRequestButtonType();
	} );

	return [ paymentRequestButtonType, updatePaymentRequestButtonType ];
};

export const usePaymentRequestButtonSize = () => {
	const { updatePaymentRequestButtonSize } = useDispatch( STORE_NAME );

	const paymentRequestButtonSize = useSelect( ( select ) => {
		const { getPaymentRequestButtonSize } = select( STORE_NAME );

		return getPaymentRequestButtonSize();
	} );

	return [ paymentRequestButtonSize, updatePaymentRequestButtonSize ];
};

export const usePaymentRequestButtonTheme = () => {
	const { updatePaymentRequestButtonTheme } = useDispatch( STORE_NAME );

	const paymentRequestButtonTheme = useSelect( ( select ) => {
		const { getPaymentRequestButtonTheme } = select( STORE_NAME );

		return getPaymentRequestButtonTheme();
	} );

	return [ paymentRequestButtonTheme, updatePaymentRequestButtonTheme ];
};

export const useGetSavingError = () => {
	return useSelect( ( select ) => {
		const { getSavingError } = select( STORE_NAME );

		return getSavingError();
	} );
};
