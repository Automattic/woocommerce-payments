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
import { getFormattedQuery } from '../../util';

/*eslint-disable camelcase*/
const paginationQueryMapping = {
	page: { source: 'paged', default: 1, isNumber: true },
	pagesize: { source: 'perPage', default: 25, isNumber: true },
	sort: { source: 'orderby', default: 'date' },
	direction: { source: 'order', default: 'desc' },
};
const filterQueryMapping = {
	date: { source: 'date', rules: [ 'before', 'after', 'between' ], isFilter: true },
	type: { source: 'type', rules: [ 'is', 'is_not' ], isFilter: true },
	deposit_id: { source: 'depositId' },
};
/*eslint-enable camelcase*/

/**
 * Retrieves a series of transactions from the transactions list API.
 *
 * @param {string} query Data on which to parameterize the selection.
 */
export function* getTransactions( query ) {
	const path = addQueryArgs(
		`${ NAMESPACE }/transactions`,
		getFormattedQuery( query, { ...paginationQueryMapping, ...filterQueryMapping } )
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
		getFormattedQuery( query, filterQueryMapping )
	);

	try {
		const summary = yield apiFetch( { path } );
		yield updateTransactionsSummary( query, summary );
	} catch ( e ) {
		yield updateErrorForTransactionsSummary( query, null, e );
	}
}
