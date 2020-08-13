/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import { updateCharge, updateErrorForCharge } from './actions';

export function* getCharge( id ) {
	try {
		const results = yield apiFetch( {
			path: `${ NAMESPACE }/charges/${ id }`,
		} );
		yield updateCharge( id, results );
	} catch ( e ) {
		yield updateErrorForCharge( id, null, e );
	}
}
