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

export function updateIsClientSecretEncryptionEnabled( isEnabled ) {
	return updateSettingsValues( {
		is_client_secret_encryption_enabled: isEnabled,
	} );
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

export function updateAvailablePaymentMethodIds( methodIds ) {
	return updateSettingsValues( {
		available_payment_method_ids: [ ...methodIds ],
	} );
}

export function updateIsSavingSettings( isSaving, error ) {
	return {
		type: ACTION_TYPES.SET_IS_SAVING_SETTINGS,
		isSaving,
		error,
	};
}

export function updateSelectedPaymentMethod( id ) {
	return {
		type: ACTION_TYPES.SET_SELECTED_PAYMENT_METHOD,
		id,
	};
}

export function updateUnselectedPaymentMethod( id ) {
	return {
		type: ACTION_TYPES.SET_UNSELECTED_PAYMENT_METHOD,
		id,
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

export function updateAccountStatementDescriptorKanji(
	accountStatementDescriptorKanji
) {
	return updateSettingsValues( {
		account_statement_descriptor_kanji: accountStatementDescriptorKanji,
	} );
}

export function updateAccountStatementDescriptorKana(
	accountStatementDescriptorKana
) {
	return updateSettingsValues( {
		account_statement_descriptor_kana: accountStatementDescriptorKana,
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

export function updateDepositScheduleInterval( depositScheduleInterval ) {
	return updateSettingsValues( {
		deposit_schedule_interval: depositScheduleInterval,
	} );
}
export function updateDepositScheduleWeeklyAnchor(
	depositScheduleWeeklyAnchor
) {
	return updateSettingsValues( {
		deposit_schedule_weekly_anchor: depositScheduleWeeklyAnchor,
	} );
}
export function updateDepositScheduleMonthlyAnchor(
	depositScheduleMonthlyAnchor
) {
	return updateSettingsValues( {
		deposit_schedule_monthly_anchor:
			depositScheduleMonthlyAnchor === ''
				? null
				: parseInt( depositScheduleMonthlyAnchor, 10 ),
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

	return error === null;
}

export function updatePaymentRequestLocations( locations ) {
	return updateSettingsValues( {
		payment_request_enabled_locations: [ ...locations ],
	} );
}

export function updateIsWooPayEnabled( isEnabled ) {
	return updateSettingsValues( { is_woopay_enabled: isEnabled } );
}

export function updateWooPayCustomMessage( message ) {
	return updateSettingsValues( {
		woopay_custom_message: message,
	} );
}

export function updateWooPayStoreLogo( storeLogo ) {
	return updateSettingsValues( {
		woopay_store_logo: storeLogo,
	} );
}

export function updateWooPayLocations( locations ) {
	return updateSettingsValues( {
		woopay_enabled_locations: [ ...locations ],
	} );
}

export function updateProtectionLevel( level ) {
	return updateSettingsValues( { current_protection_level: level } );
}

export function updateAdvancedFraudProtectionSettings( settings ) {
	return updateSettingsValues( {
		advanced_fraud_protection_settings: settings,
	} );
}

export function updateIsStripeBillingEnabled( isEnabled ) {
	return updateSettingsValues( { is_stripe_billing_enabled: isEnabled } );
}

export function updateIsSchedulingMigration( isScheduling ) {
	return {
		type: ACTION_TYPES.SCHEDULING_SUBSCRIPTION_MIGRATION,
		isScheduling,
	};
}

export function* submitStripeBillingSubscriptionMigration() {
	try {
		yield dispatch( STORE_NAME ).startResolution(
			'scheduleStripeBillingMigration'
		);

		yield apiFetch( {
			path: `${ NAMESPACE }/settings/schedule-stripe-billing-migration`,
			method: 'post',
		} );
	} catch ( e ) {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__(
				'Error starting the Stripe Billing migration.',
				'woocommerce-payments'
			)
		);
	}

	yield dispatch( STORE_NAME ).finishResolution(
		'scheduleStripeBillingMigration'
	);
}
