/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import directApiFetch from '@wordpress/api-fetch';
import { dispatch, select } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal Dependencies
 */
import { recordEvent } from 'multi-currency/interface/functions';
import TYPES from './action-types';
import { NAMESPACE, STORE_NAME } from './constants';

export function updateCurrencies( data ) {
	return {
		type: TYPES.SET_CURRENCIES,
		data,
	};
}

export function updateAvailableCurrencies( data ) {
	return {
		type: TYPES.SET_AVAILABLE_CURRENCIES,
		data,
	};
}

export function updateEnabledCurrencies( data ) {
	return {
		type: TYPES.SET_ENABLED_CURRENCIES,
		data,
	};
}

export function updateDefaultCurrency( data ) {
	return {
		type: TYPES.SET_DEFAULT_CURRENCY,
		data,
	};
}

export function updateCurrencySettings( code, data ) {
	return {
		type: TYPES.SET_CURRENCY_SETTINGS,
		data: { code, data },
	};
}

export function updateStoreSettings( data ) {
	return {
		type: TYPES.SET_STORE_SETTINGS,
		data,
	};
}

export function updateStoreSettingValues( payload ) {
	return {
		type: TYPES.SET_STORE_SETTINGS_VALUES,
		data: payload,
	};
}

export function updateIsSavingStoreSettings( isSaving, error ) {
	return {
		type: TYPES.SET_IS_SAVING_STORE_SETTINGS,
		data: { isSaving, error },
	};
}

export function* submitEnabledCurrenciesUpdate( currencies ) {
	const enabledCurrencies = Object.keys(
		select( STORE_NAME ).getEnabledCurrencies()
	);
	const addedCurrencies = currencies.filter(
		( currency ) => ! enabledCurrencies.includes( currency )
	);
	const removedCurrencies = enabledCurrencies.filter(
		( currency ) => ! currencies.includes( currency )
	);

	try {
		const result = yield apiFetch( {
			path: `${ NAMESPACE }/multi-currency/update-enabled-currencies`,
			method: 'POST',
			data: {
				enabled: currencies,
			},
		} );

		yield updateCurrencies( result );

		yield dispatch( 'core/notices' ).createSuccessNotice(
			__( 'Enabled currencies updated.', 'woocommerce-payments' )
		);

		recordEvent( 'wcpay_multi_currency_enabled_currencies_updated', {
			added_currencies: addedCurrencies,
			removed_currencies: removedCurrencies,
		} );
	} catch ( e ) {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error updating enabled currencies.', 'woocommerce-payments' )
		);
	}
}

export function* submitCurrencySettings( currencyCode, settings ) {
	try {
		const result = yield apiFetch( {
			path: `${ NAMESPACE }/multi-currency/currencies/${ currencyCode }`,
			method: 'POST',
			data: settings,
		} );

		yield updateCurrencySettings( currencyCode, result );

		yield dispatch( 'core/notices' ).createSuccessNotice(
			__( 'Currency settings updated.', 'woocommerce-payments' )
		);
	} catch ( e ) {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error updating currency settings.', 'woocommerce-payments' )
		);
	}
}

export function* saveStoreSettings( suppressNotices = false ) {
	let error = null;
	try {
		const storeSettings = select( STORE_NAME ).getStoreSettings();

		yield updateIsSavingStoreSettings( true, null );

		const result = yield apiFetch( {
			path: `${ NAMESPACE }/multi-currency/update-settings`,
			method: 'POST',
			data: {
				wcpay_multi_currency_enable_auto_currency: storeSettings.enable_auto_currency
					? 'yes'
					: 'no',
				wcpay_multi_currency_enable_storefront_switcher: storeSettings.enable_storefront_switcher
					? 'yes'
					: 'no',
				wcpay_multi_currency_rendering_mode:
					storeSettings.rendering_mode || 'speed',
			},
		} );

		yield updateStoreSettings( result );

		if ( ! suppressNotices ) {
			yield dispatch( 'core/notices' ).createSuccessNotice(
				__( 'Store settings saved.', 'woocommerce-payments' )
			);
		}
	} catch ( e ) {
		error = e;
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error saving store settings.', 'woocommerce-payments' )
		);
	} finally {
		yield updateIsSavingStoreSettings( false, error );
	}
}

export function saveOption( optionName, value ) {
	directApiFetch( {
		path: `${ NAMESPACE }/settings/${ optionName }`,
		method: 'post',
		data: { value },
	} ).catch( () => {
		dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error saving option', 'woocommerce-payments' )
		);
	} );
}
