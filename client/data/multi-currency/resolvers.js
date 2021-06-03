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
import { updateCurrencies } from './actions';

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
