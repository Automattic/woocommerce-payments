/**
 * External Dependencies
 *
 * @format
 */

/**
 * Internal Dependencies
 */
import TYPES from './action-types';

export function updateCharge( id, data ) {
	return {
		type: TYPES.SET_CHARGE,
		id,
		data,
	};
}

export function updateErrorForCharge( id, data, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_CHARGE,
		id,
		data,
		error,
	};
}
