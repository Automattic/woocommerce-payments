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
import {
	updateDocuments,
	updateErrorForDocuments,
	updateDocumentsSummary,
	updateErrorForDocumentsSummary,
} from './actions';
import { formatDateValue } from 'utils';

export const formatQueryFilters = ( query ) => ( {
	user_email: query.userEmail,
	match: query.match,
	date_before: formatDateValue( query.dateBefore, true ),
	date_after: formatDateValue( query.dateAfter ),
	date_between: query.dateBetween && [
		formatDateValue( query.dateBetween[ 0 ] ),
		formatDateValue( query.dateBetween[ 1 ], true ),
	],
	type_is: query.typeIs,
	type_is_not: query.typeIsNot,
} );

/**
 * Retrieves a series of documents from the documents list API.
 *
 * @param {string} query Data on which to parameterize the selection.
 */
export function* getDocuments( query ) {
	const path = addQueryArgs( `${ NAMESPACE }/documents`, {
		page: query.paged,
		pagesize: query.perPage,
		sort: query.orderby,
		direction: query.order,
		...formatQueryFilters( query ),
	} );

	try {
		const results = yield apiFetch( { path } );
		yield updateDocuments( query, results.data || [] );
	} catch ( e ) {
		yield dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving documents.', 'woocommerce-payments' )
		);
		yield updateErrorForDocuments( query, null, e );
	}
}

/**
 * Retrieves the documents summary from the summary API.
 *
 * @param {string} query Data on which to parameterize the selection.
 */
export function* getDocumentsSummary( query ) {
	const path = addQueryArgs(
		`${ NAMESPACE }/documents/summary`,
		formatQueryFilters( query )
	);

	try {
		const summary = yield apiFetch( { path } );
		yield updateDocumentsSummary( query, summary );
	} catch ( e ) {
		yield updateErrorForDocumentsSummary( query, null, e );
	}
}
