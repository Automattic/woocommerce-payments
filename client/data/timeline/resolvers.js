/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import { updateTimeline, updateErrorForTimeline } from './actions';

export function* getTimeline( intentionId ) {
	try {
		const results = yield apiFetch( {
			path: `${ NAMESPACE }/timeline/${ intentionId }`,
		} );
		yield updateTimeline( intentionId, results.data );
	} catch ( error ) {
		yield updateErrorForTimeline( intentionId, error );
	}
}
