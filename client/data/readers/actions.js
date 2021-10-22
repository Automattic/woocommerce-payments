/** @format */

/**
 * Internal Dependencies
 */
import TYPES from './action-types';

export function updateReaderStats( id, data ) {
	return {
		type: TYPES.SET_READER_STATS,
		id,
		data,
	};
}

export function updateErrorForReaderStats( id, data, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_READER_STATS,
		id,
		data,
		error,
	};
}
