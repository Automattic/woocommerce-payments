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
import { updateSettings, updateFetchingStatus } from './actions';

/**
 * Retrieve settings from the site's REST API.
 */
export function* getSettings() {
	const path = `${ NAMESPACE }/settings`;

	try {
		updateFetchingStatus( 'pending' );
		const result = yield apiFetch( { path } );
		yield updateSettings( result );
	} catch ( e ) {
		updateFetchingStatus( 'rejected' );
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error retrieving settings.', 'woocommerce-payments' )
		);
	}
}
