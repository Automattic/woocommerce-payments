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

export function updateIsSavedCardsEnabled( isEnabled ) {
	return updateSettingsValues( { is_saved_cards_enabled: isEnabled } );
}

export function updateIsCardPresentEligible( isEnabled ) {
	return updateSettingsValues( { is_card_present_eligible: isEnabled } );
}

export function updatePaymentRequestButtonType( type ) {
	return updateSettingsValues( { payment_request_button_type: type } );
}

export function updatePaymentRequestButtonSize( size ) {
	return updateSettingsValues( { payment_request_button_size: size } );
}

export function updatePaymentRequestButtonTheme( theme ) {
	return updateSettingsValues( { payment_request_button_theme: theme } );
}

export function updateSettings( data ) {
	return {
		type: ACTION_TYPES.SET_SETTINGS,
		data,
	};
}

export function updateIsWCPayEnabled( isEnabled ) {
	return updateSettingsValues( { is_wcpay_enabled: isEnabled } );
}

export function updateIsPaymentRequestEnabled( isEnabled ) {
	return updateSettingsValues( { is_payment_request_enabled: isEnabled } );
}

export function updateEnabledPaymentMethodIds( methodIds ) {
	return updateSettingsValues( {
		enabled_payment_method_ids: [ ...methodIds ],
	} );
}

export function updateIsSavingSettings( isSaving, error ) {
	return {
		type: ACTION_TYPES.SET_IS_SAVING_SETTINGS,
		isSaving,
		error,
	};
}

export function updateIsManualCaptureEnabled( isEnabled ) {
	return updateSettingsValues( { is_manual_capture_enabled: isEnabled } );
}

export function updateIsTestModeEnabled( isEnabled ) {
	return updateSettingsValues( { is_test_mode_enabled: isEnabled } );
}

export function updateIsDebugLogEnabled( isEnabled ) {
	return updateSettingsValues( { is_debug_log_enabled: isEnabled } );
}

export function updateIsMultiCurrencyEnabled( isEnabled ) {
	return updateSettingsValues( { is_multi_currency_enabled: isEnabled } );
}

export function updateIsWCPaySubscriptionsEnabled( isEnabled ) {
	return updateSettingsValues( {
		is_wcpay_subscriptions_enabled: isEnabled,
	} );
}

export function updateAccountStatementDescriptor( accountStatementDescriptor ) {
	return updateSettingsValues( {
		account_statement_descriptor: accountStatementDescriptor,
	} );
}

export function updateAccountBusinessName( accountBusinessName ) {
	return updateSettingsValues( {
		account_business_name: accountBusinessName,
	} );
}

export function updateAccountBusinessURL( accountBusinessURL ) {
	return updateSettingsValues( {
		account_business_url: accountBusinessURL,
	} );
}

export function updateAccountBusinessSupportAddress(
	accountBusinessSupportAddress
) {
	return updateSettingsValues( {
		account_business_support_address: accountBusinessSupportAddress,
	} );
}

export function updateAccountBusinessSupportEmail(
	accountBusinessSupportEmail
) {
	return updateSettingsValues( {
		account_business_support_email: accountBusinessSupportEmail,
	} );
}

export function updateAccountBusinessSupportPhone(
	accountBusinessSupportPhone
) {
	return updateSettingsValues( {
		account_business_support_phone: accountBusinessSupportPhone,
	} );
}

export function updateAccountBrandingLogo( accountBrandingLogo ) {
	return updateSettingsValues( {
		account_branding_logo: accountBrandingLogo,
	} );
}

export function updateAccountBrandingIcon( accountBrandingIcon ) {
	return updateSettingsValues( {
		account_branding_icon: accountBrandingIcon,
	} );
}

export function updateAccountBrandingPrimaryColor(
	accountBrandingPrimaryColor
) {
	return updateSettingsValues( {
		account_branding_primary_color: accountBrandingPrimaryColor,
	} );
}

export function updateAccountBrandingSecondaryColor(
	accountBrandingSecondaryColor
) {
	return updateSettingsValues( {
		account_branding_secondary_color: accountBrandingSecondaryColor,
	} );
}

export function* saveSettings() {
	let error = null;
	try {
		const settings = select( STORE_NAME ).getSettings();

		yield updateIsSavingSettings( true, null );

		yield apiFetch( {
			path: `${ NAMESPACE }/settings`,
			method: 'post',
			data: settings,
		} );

		yield dispatch( 'core/notices' ).createSuccessNotice(
			__( 'Settings saved.', 'woocommerce-payments' )
		);
	} catch ( e ) {
		error = e;
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error saving settings.', 'woocommerce-payments' )
		);
	} finally {
		yield updateIsSavingSettings( false, error );
	}

	return null === error;
}

export function updatePaymentRequestLocations( locations ) {
	return updateSettingsValues( {
		payment_request_enabled_locations: [ ...locations ],
	} );
}
