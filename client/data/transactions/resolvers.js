/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { addQueryArgs } from '@wordpress/url';

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
import { formatDateValue } from '../../util';

/*eslint-disable camelcase*/
const formatQueryFilters = ( query ) => ( {
	match: query.match,
	date_before: formatDateValue( query.dateBefore ),
	date_after: formatDateValue( query.dateAfter, true ),
	date_between: query.dateBetween && [
		formatDateValue( query.dateBetween[ 0 ] ),
		formatDateValue( query.dateBetween[ 1 ], true ),
	],
	type_is: query.typeIs,
	type_is_not: query.typeIsNot,
	deposit_id: query.depositId,
} );
/*eslint-enable camelcase*/

/**
 * Retrieves a series of transactions from the transactions list API.
 *
 * @param {string} query Data on which to parameterize the selection.
 */
export function* getTransactions( query ) {
	const path = addQueryArgs(
		`${ NAMESPACE }/transactions`,
		{
			page: query.paged,
			pagesize: query.perPage,
			sort: query.orderby,
			direction: query.order,
			...formatQueryFilters( query ),
		}
	);

	try {
		// TODO include filters in prefetched path.
		const unfilteredPath = addQueryArgs(
			`${ NAMESPACE }/transactions`,
			{
				page: query.paged,
				pagesize: query.perPage,
				sort: query.orderby,
				direction: query.order,
				/* eslint-disable-next-line camelcase */
				deposit_id: query.depositId,
			}
		);
		const prefetched = window.wcpaySettings.prefetchedData[ unfilteredPath ];
		const results = prefetched ? prefetched.data : yield apiFetch( { path } );
		yield updateTransactions( query, results.data || [] );
	} catch ( e ) {
		yield updateErrorForTransactions( query, null, e );
	}
}

/**
 * Retrieves the transactions summary from the summary API.
 *
 * @param {string} query Data on which to parameterize the selection.
 */
export function* getTransactionsSummary( query ) {
	const path = addQueryArgs(
		`${ NAMESPACE }/transactions/summary`,
		formatQueryFilters( query ),
	);

	try {
		// TODO include filters in prefetched path.
		const unfilteredPath = addQueryArgs(
			`${ NAMESPACE }/transactions/summary`,
			{
				/* eslint-disable-next-line camelcase */
				deposit_id: query.depositId,
			}
		);
		const prefetched = window.wcpaySettings.prefetchedData[ unfilteredPath ];
		const summary = prefetched ? prefetched.data : yield apiFetch( { path } );
		yield updateTransactionsSummary( query, summary );
	} catch ( e ) {
		yield updateErrorForTransactionsSummary( query, null, e );
	}
}
