/** @format */

/**
 * Internal Dependencies
 */
import TYPES from './action-types';

export function updateCardReaderStats( id, data ) {
	return {
		type: TYPES.SET_CARD_READER_STATS,
		id,
		data,
	};
}

export function updateCardReaders( query, data ) {
	return {
		type: TYPES.SET_READERS,
		query,
		data,
	};
}

export function updateErrorForCardReaderStats( id, data, error ) {
	return {
		type: TYPES.SET_CARD_ERROR_FOR_READER_STATS,
		id,
		data,
		error,
	};
}

export function updateErrorForCardReaders( data, error ) {
	return {
		type: TYPES.SET_CARD_ERROR_FOR_READERS,
		data,
		error,
	};
}
