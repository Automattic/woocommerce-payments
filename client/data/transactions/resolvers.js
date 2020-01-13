/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import { updateTransactionsForPage, updateErrorForPage } from './actions';

export function* getTransactionsPage( page = 1 ) {
	const url = `${ NAMESPACE }/transactions`;
	try {
		const results = yield apiFetch( { path: url } );
		yield updateTransactionsForPage( page, results.data );
	} catch ( e ) {
		yield updateErrorForPage( page, null, e );
	}
}
