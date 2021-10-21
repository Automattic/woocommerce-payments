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

	const isSavedCardsEnabled = useSelect( ( select ) => {
		return select( STORE_NAME ).getIsSavedCardsEnabled();
	}, [] );

	return [ isSavedCardsEnabled, updateIsSavedCardsEnabled ];
};

export const useCardPresentEligible = () => {
	const { updateIsCardPresentEligible } = useDispatch( STORE_NAME );

	const isCardPresentEligible = useSelect( ( select ) => {
		return select( STORE_NAME ).getIsCardPresentEligible();
	}, [] );

	return [ isCardPresentEligible, updateIsCardPresentEligible ];
};

export const useEnabledPaymentMethodIds = () => {
	const { updateEnabledPaymentMethodIds } = useDispatch( STORE_NAME );

	return useSelect(
		( select ) => {
			const { getEnabledPaymentMethodIds } = select( STORE_NAME );

			return [
				getEnabledPaymentMethodIds(),
				updateEnabledPaymentMethodIds,
			];
		},
		[ updateEnabledPaymentMethodIds ]
	);
};

export const useDebugLog = () => {
	const { updateIsDebugLogEnabled } = useDispatch( STORE_NAME );

	return useSelect(
		( select ) => {
			const { getIsDebugLogEnabled } = select( STORE_NAME );

			return [ getIsDebugLogEnabled(), updateIsDebugLogEnabled ];
		},
		[ updateIsDebugLogEnabled ]
	);
};

export const useTestMode = () => {
	const { updateIsTestModeEnabled } = useDispatch( STORE_NAME );

	return useSelect(
		( select ) => {
			const { getIsTestModeEnabled } = select( STORE_NAME );

			return [ getIsTestModeEnabled(), updateIsTestModeEnabled ];
		},
		[ updateIsTestModeEnabled ]
	);
};

export const useDevMode = () => {
	return useSelect( ( select ) => {
		const { getIsDevModeEnabled } = select( STORE_NAME );

		return getIsDevModeEnabled();
	}, [] );
};

export const useMultiCurrency = () => {
	const { updateIsMultiCurrencyEnabled } = useDispatch( STORE_NAME );

	return useSelect(
		( select ) => {
			const { getIsMultiCurrencyEnabled } = select( STORE_NAME );
			const isMultiCurrencyEnabled = getIsMultiCurrencyEnabled();
			return [ isMultiCurrencyEnabled, updateIsMultiCurrencyEnabled ];
		},
		[ updateIsMultiCurrencyEnabled ]
	);
};

export const useWCPaySubscriptions = () => {
	const { updateIsWCPaySubscriptionsEnabled } = useDispatch( STORE_NAME );

	return useSelect(
		( select ) => {
			const {
				getIsWCPaySubscriptionsEnabled,
				getIsWCPaySubscriptionsEligible,
				getIsSubscriptionsPluginActive,
			} = select( STORE_NAME );

			const isWCPaySubscriptionsEnabled = getIsWCPaySubscriptionsEnabled();
			const isWCPaySubscriptionsEligible = getIsWCPaySubscriptionsEligible();
			const isSubscriptionsPluginActive = getIsSubscriptionsPluginActive();

			return [
				isWCPaySubscriptionsEnabled,
				isWCPaySubscriptionsEligible,
				isSubscriptionsPluginActive,
				updateIsWCPaySubscriptionsEnabled,
			];
		},
		[ updateIsWCPaySubscriptionsEnabled ]
	);
};

export const useAccountStatementDescriptor = () => {
	const { updateAccountStatementDescriptor } = useDispatch( STORE_NAME );

	return useSelect(
		( select ) => {
			const { getAccountStatementDescriptor } = select( STORE_NAME );

			return [
				getAccountStatementDescriptor(),
				updateAccountStatementDescriptor,
			];
		},
		[ updateAccountStatementDescriptor ]
	);
};

export const useManualCapture = () => {
	const { updateIsManualCaptureEnabled } = useDispatch( STORE_NAME );

	return useSelect(
		( select ) => {
			const { getIsManualCaptureEnabled } = select( STORE_NAME );

			return [
				getIsManualCaptureEnabled(),
				updateIsManualCaptureEnabled,
			];
		},
		[ updateIsManualCaptureEnabled ]
	);
};

export const useIsWCPayEnabled = () => {
	const { updateIsWCPayEnabled } = useDispatch( STORE_NAME );

	return useSelect(
		( select ) => {
			const { getIsWCPayEnabled } = select( STORE_NAME );

			return [ getIsWCPayEnabled(), updateIsWCPayEnabled ];
		},
		[ updateIsWCPayEnabled ]
	);
};

export const useGetAvailablePaymentMethodIds = () =>
	useSelect( ( select ) => {
		const { getAvailablePaymentMethodIds } = select( STORE_NAME );

		return getAvailablePaymentMethodIds();
	} );

export const useGetPaymentMethodStatuses = () =>
	useSelect( ( select ) => {
		const { getPaymentMethodStatuses } = select( STORE_NAME );

		return getPaymentMethodStatuses();
	} );

export const useSettings = () => {
	const { saveSettings } = useDispatch( STORE_NAME );

	return useSelect(
		( select ) => {
			const {
				getSettings,
				hasFinishedResolution,
				isResolving,
				isSavingSettings,
			} = select( STORE_NAME );

			const isLoading =
				isResolving( 'getSettings' ) ||
				! hasFinishedResolution( 'getSettings' );

			return {
				settings: getSettings(),
				isLoading,
				saveSettings,
				isSaving: isSavingSettings(),
			};
		},
		[ saveSettings ]
	);
};

export const usePaymentRequestEnabledSettings = () => {
	const { updateIsPaymentRequestEnabled } = useDispatch( STORE_NAME );

	return useSelect( ( select ) => {
		const { getIsPaymentRequestEnabled } = select( STORE_NAME );

		return [ getIsPaymentRequestEnabled(), updateIsPaymentRequestEnabled ];
	} );
};

export const usePaymentRequestLocations = () => {
	const { updatePaymentRequestLocations } = useDispatch( STORE_NAME );

	return useSelect( ( select ) => {
		const { getPaymentRequestLocations } = select( STORE_NAME );

		return [ getPaymentRequestLocations(), updatePaymentRequestLocations ];
	} );
};

export const usePaymentRequestButtonType = () => {
	const { updatePaymentRequestButtonType } = useDispatch( STORE_NAME );

	return useSelect( ( select ) => {
		const { getPaymentRequestButtonType } = select( STORE_NAME );

		return [
			getPaymentRequestButtonType(),
			updatePaymentRequestButtonType,
		];
	} );
};

export const usePaymentRequestButtonSize = () => {
	const { updatePaymentRequestButtonSize } = useDispatch( STORE_NAME );

	return useSelect( ( select ) => {
		const { getPaymentRequestButtonSize } = select( STORE_NAME );

		return [
			getPaymentRequestButtonSize(),
			updatePaymentRequestButtonSize,
		];
	} );
};

export const usePaymentRequestButtonTheme = () => {
	const { updatePaymentRequestButtonTheme } = useDispatch( STORE_NAME );

	return useSelect( ( select ) => {
		const { getPaymentRequestButtonTheme } = select( STORE_NAME );

		return [
			getPaymentRequestButtonTheme(),
			updatePaymentRequestButtonTheme,
		];
	} );
};

export const useGetSavingError = () => {
	return useSelect( ( select ) => {
		const { getSavingError } = select( STORE_NAME );

		return getSavingError();
	}, [] );
};
