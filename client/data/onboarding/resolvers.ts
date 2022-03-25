/** @format */

/**
 * External dependencies
 */
import { apiFetch, dispatch } from '@wordpress/data-controls';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import { updateBusinessTypes } from './actions';
import { Country } from './types';

/**
 * Retrieve business types from the site's REST API.
 */
export function* getBusinessTypes(): unknown {
	const path = `${ NAMESPACE }/onboarding/business_types`;

	try {
		const result = yield apiFetch( { path } );
		yield updateBusinessTypes( result.data as Country[] );
	} catch ( e ) {
		yield dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving business types', 'woocommerce-payments' )
		);
	}
}
