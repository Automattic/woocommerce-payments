/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal Dependencies
 */
import TYPES from './action-types';
import { NAMESPACE } from '../constants';

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

export function* submitEnabledCurrenciesUpdate( currencies ) {
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
	} catch ( e ) {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error updating enabled currencies.', 'woocommerce-payments' )
		);
	}
}
