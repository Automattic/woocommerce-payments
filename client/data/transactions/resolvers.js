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

export function* getTransactions( { paged = '1', perPage = '25', depositId = null } ) {
	const path = addQueryArgs(
		`${ NAMESPACE }/transactions`,
		{
			page: paged,
			pagesize: perPage,
			/* eslint-disable-next-line camelcase */
			deposit_id: depositId,
		}
	);

	try {
		const results = yield apiFetch( { path } );
		yield updateTransactions( { paged, perPage, depositId }, results.data || [] );
	} catch ( e ) {
		yield updateErrorForTransactions( { paged, perPage, depositId }, null, e );
	}
}

/**
 * Retrieves the transactions summary from the summary API.
 */
export function* getTransactionsSummary( { depositId = null } ) {
	const path = addQueryArgs(
		`${ NAMESPACE }/transactions/summary`,
		{
			/* eslint-disable-next-line camelcase */
			deposit_id: depositId,
		}
	);

	try {
		const summary = yield apiFetch( { path } );
		yield updateTransactionsSummary( { depositId }, summary );
	} catch ( e ) {
		yield updateErrorForTransactionsSummary( { depositId }, null, e );
	}
}
