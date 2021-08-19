/** @format */

/**
 * External dependencies
 */
import { dispatch } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAMESPACE, STORE_NAME } from '../constants';
import { updateDispute, updateDisputes } from './actions';

/**
 * Retrieve a single dispute from the disputes API.
 *
 * @param {string} id Identifier for specified dispute to retrieve.
 */
export function* getDispute( id ) {
	const path = addQueryArgs( `${ NAMESPACE }/disputes/${ id }` );

	try {
		const result = yield apiFetch( { path } );
		yield updateDispute( result );
	} catch ( e ) {
		yield dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving dispute.', 'woocommerce-payments' )
		);
	}
}

/**
 * Retrieves a series of disputes from the disputes list API.
 *
 * @param {string} query Data on which to parameterize the selection.
 */
export function* getDisputes( query ) {
	const path = addQueryArgs( `${ NAMESPACE }/disputes`, {
		page: query.paged,
		pagesize: query.perPage,
	} );

	try {
		const results = yield apiFetch( { path } ) || {};
		yield updateDisputes( query, results.data );

		// Update resolution state on getDispute selector for each result.
		for ( const i in results.data ) {
			yield dispatch( STORE_NAME, 'finishResolution', 'getDispute', [
				results.data[ i ].id,
			] );
		}
	} catch ( e ) {
		yield dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving disputes.', 'woocommerce-payments' )
		);
	}
}
