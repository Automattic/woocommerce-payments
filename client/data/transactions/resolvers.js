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

export function* getTransactions( query ) {
	const path = addQueryArgs(
		`${ NAMESPACE }/transactions`,
		{
			page: query.paged,
			pagesize: query.perPage,
			/* eslint-disable-next-line camelcase */
			deposit_id: query.depositId,
		}
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
 */
export function* getTransactionsSummary( query ) {
	const path = addQueryArgs(
		`${ NAMESPACE }/transactions/summary`,
		{
			/* eslint-disable-next-line camelcase */
			deposit_id: query.depositId,
		}
	);

	try {
		const summary = yield apiFetch( { path } );
		yield updateTransactionsSummary( query, summary );
	} catch ( e ) {
		yield updateErrorForTransactionsSummary( query, null, e );
	}
}
