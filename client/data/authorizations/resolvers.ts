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
import { updateAuthorizations, updateAuthorizationsSummary } from './actions';
import { Query } from '@woocommerce/navigation';

export function* getAuthorizations( query: Query ): any {
	const path = addQueryArgs( `${ NAMESPACE }/authorizations`, {
		test: 1,
		pagesize: query.per_page,
		sort: query.orderby,
		direction: query.order,
		page: query.paged,
	} );

	try {
		const results = yield apiFetch( { path } );
		yield updateAuthorizations( query, results.data || [] );
	} catch ( e ) {
		yield dispatch(
			'core/notices',
			'createErrorNotice',
			__(
				'Error retrieving uncaptured transactions.',
				'woocommerce-payments'
			)
		);
		// TODO: Add error handling
	}
}

export function* getAuthorizationsSummary( query: Query ): any {
	const path = addQueryArgs( `${ NAMESPACE }/authorizations/summary`, {
		test: 1,
	} );

	try {
		const results = yield apiFetch( { path } );
		yield updateAuthorizationsSummary( query, results || [] );
	} catch ( e ) {
		yield dispatch(
			'core/notices',
			'createErrorNotice',
			__(
				'Error retrieving uncaptured transactions.',
				'woocommerce-payments'
			)
		);
		// TODO: Add error handling
	}
}
