/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { dispatch, select } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal Dependencies
 */
import { recordEvent } from 'multi-currency/interface/data';
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

export function* submitStoreSettingsUpdate(
	isAutoSwitchEnabled,
	isStorefrontSwitcherEnabled,
	suppressNotices = false
) {
	try {
		const result = yield apiFetch( {
			path: `${ NAMESPACE }/multi-currency/update-settings`,
			method: 'POST',
			data: {
				wcpay_multi_currency_enable_auto_currency: isAutoSwitchEnabled
					? 'yes'
					: 'no',
				wcpay_multi_currency_enable_storefront_switcher: isStorefrontSwitcherEnabled
					? 'yes'
					: 'no',
			},
		} );

		yield updateStoreSettings( result );

		if ( suppressNotices ) return;

		yield dispatch( 'core/notices' ).createSuccessNotice(
			__( 'Store settings saved.', 'woocommerce-payments' )
		);
	} catch ( e ) {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error saving store settings.', 'woocommerce-payments' )
		);
	}
}
