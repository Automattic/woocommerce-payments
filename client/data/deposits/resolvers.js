/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAMESPACE, STORE_NAME } from '../constants';
import {
	updateDeposit,
	updateDeposits,
	updateDepositsCount,
	updateErrorForDepositQuery,
	updateDepositsSummary,
	updateErrorForDepositsSummary,
	updateAllDepositsOverviews,
	updateErrorForAllDepositsOverviews,
} from './actions';
import { formatDateValue } from 'utils';

/**
 * Retrieve a single deposit from the deposits API.
 *
 * @param {string} id Identifier for specified deposit to retrieve.
 */
export function* getDeposit( id ) {
	// Validate input to avoid path traversal request.
	if ( /[.\/?#&]/.test( id ) ) {
		return;
	}

	const path = addQueryArgs( `${ NAMESPACE }/deposits/${ id }` );

	try {
		const result = yield apiFetch( { path } );
		yield updateDeposit( result );
	} catch ( e ) {
		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving deposit.', 'woocommerce-payments' )
		);
	}
}

/**
 * Retrieve all deposits' overviews from the deposits API.
 */
export function* getAllDepositsOverviews() {
	const path = addQueryArgs( `${ NAMESPACE }/deposits/overview-all` );

	try {
		const result = yield apiFetch( { path } );
		yield updateAllDepositsOverviews( result );
	} catch ( e ) {
		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			__(
				"Error retrieving all deposits' overviews.",
				'woocommerce-payments'
			)
		);
		yield updateErrorForAllDepositsOverviews( e );
	}
}

const formatQueryFilters = ( query ) => ( {
	user_email: query.userEmail,
	match: query.match,
	store_currency_is: query.storeCurrencyIs,
	date_before: formatDateValue( query.dateBefore, true ),
	date_after: formatDateValue( query.dateAfter ),
	date_between: query.dateBetween && [
		formatDateValue( query.dateBetween[ 0 ] ),
		formatDateValue( query.dateBetween[ 1 ], true ),
	],
	status_is: query.statusIs,
	status_is_not: query.statusIsNot,
	locale: query.locale,
} );

export function getDepositsCSV( query ) {
	const path = addQueryArgs(
		`${ NAMESPACE }/deposits/download`,
		formatQueryFilters( query )
	);

	return path;
}

/**
 * Retrieves a series of deposits from the deposits list API.
 *
 * @param {string} query Data on which to parameterize the selection.
 */
export function* getDeposits( query ) {
	const path = addQueryArgs( `${ NAMESPACE }/deposits`, {
		page: query.paged,
		pagesize: query.perPage,
		sort: query.orderby,
		direction: query.order,
		...formatQueryFilters( query ),
	} );

	try {
		const results = yield apiFetch( { path } ) || {};

		yield updateDeposits( query, results.data );
		yield updateDepositsCount( results.total_count );

		// Update resolution state on getDeposit selector for each result.
		for ( const i in results.data ) {
			yield controls.dispatch(
				STORE_NAME,
				'finishResolution',
				'getDeposit',
				[ results.data[ i ].id ]
			);
		}
	} catch ( e ) {
		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving deposits.', 'woocommerce-payments' )
		);
		yield updateErrorForDepositQuery( query, null, e );
	}
}

/**
 * Retrieves the deposits summary from the summary API.
 *
 * @param {string} query Data on which to parameterize the selection.
 */
export function* getDepositsSummary( query ) {
	const path = addQueryArgs(
		`${ NAMESPACE }/deposits/summary`,
		formatQueryFilters( query )
	);

	try {
		const summary = yield apiFetch( { path } );
		yield updateDepositsSummary( query, summary );
	} catch ( e ) {
		yield updateErrorForDepositsSummary( query, null, e );
	}
}
