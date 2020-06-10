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
import { updateDisputes } from './actions';

/**
 * Retrieves a series of disputes from the disputes list API.
 *
 * @param {string} query Data on which to parameterize the selection.
 */
export function* getDisputes( query ) {
	const path = addQueryArgs(
		`${ NAMESPACE }/disputes`,
		{
			page: query.paged,
			pagesize: query.perPage,
		}
	);

	try {
		const results = yield apiFetch( { path } ) || {};
		yield updateDisputes( query, results.data );
	} catch ( e ) {
		yield dispatch( 'core/notices', 'createErrorNotice', __( 'Error retrieving disputes.', 'woocommerce-payments' ) );
	}
}
