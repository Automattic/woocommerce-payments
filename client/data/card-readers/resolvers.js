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
	updateErrorForCardReaderStats,
	updateCardReaderStats,
} from './actions';

export function* getCardReaderStats( id, transactionId ) {
	try {
		const results = yield apiFetch( {
			path: `${ NAMESPACE }/readers/charges/${ transactionId }`,
		} );
		yield updateCardReaderStats( id, results );
	} catch ( e ) {
		yield updateErrorForCardReaderStats( id, null, e );
	}
}
