/** @format */

/**
 * Internal Dependencies
 */
import TYPES from './action-types';

export function updateTimeline( id, data ) {
	return {
		id,
		type: TYPES.SET_TIMELINE,
		data,
	};
}

export function updateErrorForTimeline( id, error ) {
	return {
		id,
		type: TYPES.SET_ERROR_FOR_TIMELINE,
		error,
	};
}
