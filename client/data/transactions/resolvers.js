/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import { updateTransactions, updateErrorForTransactions } from './actions';

export function* getTransactions() {
	try {
		const results = yield apiFetch( { path: `${ NAMESPACE }/transactions` } );
		yield updateTransactions( results );
	} catch ( e ) {
		yield updateErrorForTransactions( null, e );
	}
}
