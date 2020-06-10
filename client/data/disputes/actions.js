/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';

export function updateDispute( data ) {
	return {
		type: TYPES.SET_DISPUTE,
		data,
	};
}

export function updateDisputes( query, data ) {
	return {
		type: TYPES.SET_DISPUTES,
		query,
		data,
	};
}
