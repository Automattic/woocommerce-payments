/** @format */

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import { updateCharge, updateErrorForCharge } from './actions';

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';

export function* getCharge( id ) {
	const url = `${ NAMESPACE }/charges/${ id }`;
	try {
		const results = yield apiFetch( { path: url } );
		yield updateCharge( id, results );
	} catch ( e ) {
		yield updateErrorForCharge( id, null, e );
	}
}
