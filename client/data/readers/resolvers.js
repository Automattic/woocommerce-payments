/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import { updateErrorForReaderStats, updateReaderStats } from './actions';

export function* getReaderStats( id ) {
	try {
		const results = yield apiFetch( {
			path: `${ NAMESPACE }/reader-charges/summary`,
		} );
		yield updateReaderStats( id, results );
	} catch ( e ) {
		yield updateErrorForReaderStats( id, null, e );
	}
}
