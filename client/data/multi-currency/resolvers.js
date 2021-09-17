/** @format */

/**
 * External dependencies
 */
import { dispatch } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import {
	updateCurrencies,
	updateCurrencySettings,
	updateStoreSettings,
} from './actions';

/**
 * Retrieve settings from the site's REST API.
 */
export function* getCurrencies() {
	const path = `${ NAMESPACE }/multi-currency/currencies`;

	try {
		const result = yield apiFetch( { path } );
		yield updateCurrencies( result );
	} catch ( e ) {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error retrieving currencies.', 'woocommerce-payments' )
		);
	}
}

/**
 * Retrieve single currency sttings from the site's REST API.
 *
 * @param {string} currencyCode The currency code to fetch settings for.
 */
export function* getCurrencySettings( currencyCode ) {
	const path = `${ NAMESPACE }/multi-currency/currencies/${ currencyCode }`;

	try {
		const result = yield apiFetch( { path } );
		yield updateCurrencySettings( currencyCode, result );
	} catch ( e ) {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__(
				'Error retrieving single currency settings.',
				'woocommerce-payments'
			)
		);
	}
}

export function* getStoreSettings() {
	const path = `${ NAMESPACE }/multi-currency/get-settings`;

	try {
		const result = yield apiFetch( { path } );
		yield updateStoreSettings( result );
	} catch ( e ) {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error retrieving store settings.', 'woocommerce-payments' )
		);
	}
}
