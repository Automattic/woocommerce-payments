/** @format */

/**
 * External dependencies
 */
import { apiFetch, dispatch } from '@wordpress/data-controls';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import { updateSettings } from './actions';

/**
 * Retrieve a single deposit from the deposits API.
 */
export function* getSettings() {
	const path = addQueryArgs( `${ NAMESPACE }/settings` );

	try {
		const result = yield apiFetch( { path } );
		yield updateSettings( result );
	} catch ( e ) {
		yield dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving settings.', 'woocommerce-payments' )
		);
	}
}
