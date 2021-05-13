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

export function updateSettings( data ) {
	return {
		type: ACTION_TYPES.SET_SETTINGS,
		data,
	};
}

export function updateIsWCPayEnabled( isEnabled ) {
	return {
		type: ACTION_TYPES.SET_IS_WCPAY_ENABLED,
		isEnabled,
	};
}

export function updateEnabledPaymentMethodIds( methodIds ) {
	return {
		type: ACTION_TYPES.SET_ENABLED_PAYMENT_METHOD_IDS,
		methodIds,
	};
}

export function updateIsSavingSettings( isSaving ) {
	return {
		type: ACTION_TYPES.SET_IS_SAVING_SETTINGS,
		isSaving,
	};
}

export function* saveSettings() {
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
	} catch ( e ) {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error saving settings.', 'woocommerce-payments' )
		);
	} finally {
		yield updateIsSavingSettings( false );
	}
}
