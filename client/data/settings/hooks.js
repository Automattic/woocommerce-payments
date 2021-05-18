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
	const { updateIsWCPayEnabled } = useDispatch( STORE_NAME );

	return useSelect(
		( select ) => {
			const { getIsWCPayEnabled } = select( STORE_NAME );

			return {
				isWCPayEnabled: getIsWCPayEnabled(),
				updateIsWCPayEnabled,
			};
		},
		[ updateIsWCPayEnabled ]
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

export const useDigitalWalletsSettings = () => {
	const { updateIsDigitalWalletsEnabled } = useDispatch( STORE_NAME );
	return useSelect( ( select ) => {
		const { getIsDigitalWalletsEnabled } = select( STORE_NAME );

		return {
			isDigitalWalletsEnabled: getIsDigitalWalletsEnabled(),
			updateIsDigitalWalletsEnabled,
		};
	} );
};

export const useDigitalWalletsSectionsToShowOn = () => {
	const { updateDigitalWalletsSectionsToShowOn } = useDispatch( STORE_NAME );

	return useSelect( ( select ) => {
		const { getDigitalWalletsSections } = select( STORE_NAME );

		return {
			digitalWalletsSections: getDigitalWalletsSections(),
			updateDigitalWalletsSectionsToShowOn,
		};
	} );
};
