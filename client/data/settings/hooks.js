/** @format */

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';

export const useEnabledPaymentMethodIds = () =>
	useSelect( ( select ) => {
		const { getEnabledPaymentMethodIds } = select( STORE_NAME );
		const { updateEnabledPaymentMethodIds } = useDispatch( STORE_NAME );

		return {
			enabledPaymentMethodIds: getEnabledPaymentMethodIds(),
			updateEnabledPaymentMethodIds,
		};
	} );

export const useGeneralSettings = () =>
	useSelect( ( select ) => {
		const { getAccountStatementDescriptor } = select( STORE_NAME );
		const { getIsWCPayEnabled } = select( STORE_NAME );
		const { getIsManualCaptureEnabled } = select( STORE_NAME );
		const { updateAccountStatementDescriptor } = useDispatch( STORE_NAME );
		const { updateIsManualCaptureEnabled } = useDispatch( STORE_NAME );
		const { updateIsWCPayEnabled } = useDispatch( STORE_NAME );

		return {
			accountStatementDescriptor: getAccountStatementDescriptor(),
			isWCPayEnabled: getIsWCPayEnabled(),
			isManualCaptureEnabled: getIsManualCaptureEnabled(),
			updateIsWCPayEnabled,
			updateAccountStatementDescriptor,
			updateIsManualCaptureEnabled,
		};
	} );

export const useSettings = () =>
	useSelect( ( select ) => {
		const {
			getSettings,
			hasFinishedResolution,
			isResolving,
			isSavingSettings,
		} = select( STORE_NAME );

		const isLoading =
			isResolving( 'getSettings' ) ||
			! hasFinishedResolution( 'getSettings' );
		const { saveSettings } = useDispatch( STORE_NAME );

		return {
			settings: getSettings(),
			isLoading,
			saveSettings,
			isSaving: isSavingSettings(),
		};
	} );
