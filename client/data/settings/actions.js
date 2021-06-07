/** @format */

/**
 * External dependencies
 */
import { dispatch, select } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import ACTION_TYPES from './action-types';
import { NAMESPACE, STORE_NAME } from '../constants';

function updateSettingsValues( payload ) {
	return {
		type: ACTION_TYPES.SET_SETTINGS_VALUES,
		payload,
	};
}

export function updateDigitalWalletsButtonType( type ) {
	// eslint-disable-next-line camelcase
	return updateSettingsValues( { digital_wallets_button_type: type } );
}

export function updateDigitalWalletsButtonSize( size ) {
	// eslint-disable-next-line camelcase
	return updateSettingsValues( { digital_wallets_button_size: size } );
}

export function updateDigitalWalletsButtonTheme( theme ) {
	// eslint-disable-next-line camelcase
	return updateSettingsValues( { digital_wallets_button_theme: theme } );
}

export function updateSettings( data ) {
	return {
		type: ACTION_TYPES.SET_SETTINGS,
		data,
	};
}

export function updateIsWCPayEnabled( isEnabled ) {
	// eslint-disable-next-line camelcase
	return updateSettingsValues( { is_wcpay_enabled: isEnabled } );
}

export function updateIsDigitalWalletsEnabled( isEnabled ) {
	// eslint-disable-next-line camelcase
	return updateSettingsValues( { is_digital_wallets_enabled: isEnabled } );
}

export function updateEnabledPaymentMethodIds( methodIds ) {
	return updateSettingsValues( {
		// eslint-disable-next-line camelcase
		enabled_payment_method_ids: [ ...methodIds ],
	} );
}

export function updateIsSavingSettings( isSaving ) {
	return {
		type: ACTION_TYPES.SET_IS_SAVING_SETTINGS,
		isSaving,
	};
}

export function updateIsManualCaptureEnabled( isEnabled ) {
	// eslint-disable-next-line camelcase
	return updateSettingsValues( { is_manual_capture_enabled: isEnabled } );
}

export function updateIsTestModeEnabled( isEnabled ) {
	// eslint-disable-next-line camelcase
	return updateSettingsValues( { is_test_mode_enabled: isEnabled } );
}

export function updateIsDebugLogEnabled( isEnabled ) {
	// eslint-disable-next-line camelcase
	return updateSettingsValues( { is_debug_log_enabled: isEnabled } );
}

export function updateAccountStatementDescriptor( accountStatementDescriptor ) {
	return updateSettingsValues( {
		// eslint-disable-next-line camelcase
		account_statement_descriptor: accountStatementDescriptor,
	} );
}

export function* saveSettings() {
	let isSuccess = false;
	try {
		const settings = select( STORE_NAME ).getSettings();

		yield updateIsSavingSettings( true );

		yield apiFetch( {
			path: `${ NAMESPACE }/settings`,
			method: 'post',
			data: settings,
		} );

		yield dispatch( 'core/notices' ).createSuccessNotice(
			__( 'Settings saved.', 'woocommerce-payments' )
		);

		isSuccess = true;
	} catch ( e ) {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error saving settings.', 'woocommerce-payments' )
		);
	} finally {
		yield updateIsSavingSettings( false );
	}

	return isSuccess;
}

export function updateDigitalWalletsLocations( locations ) {
	return updateSettingsValues( {
		// eslint-disable-next-line camelcase
		digital_wallets_enabled_locations: [ ...locations ],
	} );
}
