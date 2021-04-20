/** @format */

/**
 * Internal Dependencies
 */
import TYPES from './action-types';

export function updateSettings( data ) {
	return {
		type: TYPES.SET_SETTINGS,
		data,
	};
}
