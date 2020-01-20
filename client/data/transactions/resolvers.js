/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';

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

export function* getTransactions() {
	try {
		const results = yield apiFetch( { path: `${ NAMESPACE }/transactions` } );
		yield updateTransactions( results.data || [] );
	} catch ( e ) {
		yield updateErrorForTransactions( null, e );
	}
}

/**
 * Retrieves the transactions summary from the summary API.
 *
 * @returns {object} Action that updates the data store.
 */
export function* getTransactionsSummary() {
	try {
		const summary = yield apiFetch( { path: `${ NAMESPACE }/transactions/summary` } );
		yield updateTransactionsSummary( summary );
	} catch ( e ) {
		return updateErrorForTransactionsSummary( null, e );
	}
}
