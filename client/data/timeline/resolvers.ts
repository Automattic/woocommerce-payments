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
import { Timeline } from './types';

const isTimeline = ( responseData: any ): responseData is Timeline => {
	// We just make sure that the Timeline is an array, and trust that the data in the array are
	// timeline events.
	return Array.isArray( responseData );
};

export function* getTimeline( intentionId: string ) {
	try {
		const results = yield apiFetch( {
			path: `${ NAMESPACE }/timeline/${ intentionId }`,
		} );

		const timeline = results.data;
		if ( isTimeline( timeline ) ) {
			yield updateTimeline( intentionId, timeline );
		}
	} catch ( error ) {
		yield updateErrorForTimeline( intentionId, error );
	}
}
