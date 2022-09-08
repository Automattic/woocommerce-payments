/** @format */

/**
 * External dependencies
 */
import { apiFetch, dispatch } from '@wordpress/data-controls';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';
import moment from 'moment';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import {
	updateTransactions,
	updateErrorForTransactions,
	updateTransactionsSummary,
	updateErrorForTransactionsSummary,
} from './actions';
import { formatDateValue } from 'utils';

function getUserTimeZone() {
	return moment( new Date() ).format( 'Z' );
}

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
	store_currency_is: query.storeCurrencyIs,
	loan_id_is: query.loanIdIs,
	deposit_id: query.depositId,
	customer_currency_is: query.customerCurrencyIs,
	customer_currency_is_not: query.customerCurrencyIsNot,
	search: query.search,
	user_timezone: getUserTimeZone(),
} );

/**
 * Retrieves a series of transactions from the transactions list API.
 *
 * @param {string} query Data on which to parameterize the selection.
 */
export function* getTransactions( query ) {
	const path = addQueryArgs( `${ NAMESPACE }/transactions`, {
		page: query.paged,
		pagesize: query.perPage,
		sort: query.orderby,
		direction: query.order,
		...formatQueryFilters( query ),
	} );

	try {
		const results = yield apiFetch( { path } );
		yield updateTransactions( query, results.data || [] );
	} catch ( e ) {
		yield dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving transactions.', 'woocommerce-payments' )
		);
		yield updateErrorForTransactions( query, null, e );
	}
}

export function getTransactionsCSV( query ) {
	const path = addQueryArgs(
		`${ NAMESPACE }/transactions/download`,
		formatQueryFilters( query )
	);

	return path;
}

/**
 * Retrieves the transactions summary from the summary API.
 *
 * @param {string} query Data on which to parameterize the selection.
 */
export function* getTransactionsSummary( query ) {
	const path = addQueryArgs(
		`${ NAMESPACE }/transactions/summary`,
		formatQueryFilters( query )
	);

	try {
		const summary = yield apiFetch( { path } );
		yield updateTransactionsSummary( query, summary );
	} catch ( e ) {
		yield updateErrorForTransactionsSummary( query, null, e );
	}
}
