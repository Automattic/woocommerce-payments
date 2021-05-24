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

	return useSelect(
		( select ) => {
			const { getEnabledPaymentMethodIds } = select( STORE_NAME );

			return {
				enabledPaymentMethodIds: getEnabledPaymentMethodIds(),
				updateEnabledPaymentMethodIds,
			};
		},
		[ updateEnabledPaymentMethodIds ]
	);
};

export const useGeneralSettings = () => {
	const { updateAccountStatementDescriptor } = useDispatch( STORE_NAME );
	const { updateIsManualCaptureEnabled } = useDispatch( STORE_NAME );
	const { updateIsWCPayEnabled } = useDispatch( STORE_NAME );

	return useSelect(
		( select ) => {
			const { getAccountStatementDescriptor } = select( STORE_NAME );
			const { getIsWCPayEnabled } = select( STORE_NAME );
			const { getIsManualCaptureEnabled } = select( STORE_NAME );

			return {
				accountStatementDescriptor: getAccountStatementDescriptor(),
				isManualCaptureEnabled: getIsManualCaptureEnabled(),
				isWCPayEnabled: getIsWCPayEnabled(),
				updateAccountStatementDescriptor,
				updateIsManualCaptureEnabled,
				updateIsWCPayEnabled,
			};
		},
		[
			updateAccountStatementDescriptor,
			updateIsManualCaptureEnabled,
			updateIsWCPayEnabled,
		]
	);
};

export const useGetAvailablePaymentMethodIds = () =>
	useSelect( ( select ) => {
		const { getAvailablePaymentMethodIds } = select( STORE_NAME );

		return getAvailablePaymentMethodIds();
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
