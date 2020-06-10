/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';

export function updateDisputes( query, data ) {
	return {
		type: TYPES.SET_DISPUTES,
		query,
		data,
	};
}
