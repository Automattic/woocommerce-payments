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

/**
 * Retrieves a series of transactions from the transactions list API.
 *
 * @param {string} query Data on which to parameterize the selection.
 */
export function* getTransactions( query ) {
	const path = addQueryArgs(
		`${ NAMESPACE }/transactions`,
		/*eslint-disable camelcase*/
		{
			page: query.paged,
			pagesize: query.perPage,
			sort: query.orderby,
			direction: query.order,
			match: query.match,
			date_before: query.dateBefore,
			date_after: query.dateAfter,
			date_between: query.dateBetween,
			type_is: query.typeIs,
			type_is_not: query.typeIsNot,
			deposit_id: query.depositId,
		}
		/*eslint-enable camelcase*/
	);

	try {
		const results = yield apiFetch( { path } );
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
		/*eslint-disable camelcase*/
		{
			match: query.match,
			date_before: query.dateBefore,
			date_after: query.dateAfter,
			date_between: query.dateBetween,
			type_is: query.typeIs,
			type_is_not: query.typeIsNot,
			deposit_id: query.depositId,
		}
		/*eslint-enable camelcase*/
	);

	try {
		const summary = yield apiFetch( { path } );
		yield updateTransactionsSummary( query, summary );
	} catch ( e ) {
		yield updateErrorForTransactionsSummary( query, null, e );
	}
}
