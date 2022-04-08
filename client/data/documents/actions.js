/** @format */

/**
 * Internal Dependencies
 */
import TYPES from './action-types';

export function updateDocuments( query, data ) {
	return {
		type: TYPES.SET_DOCUMENTS,
		query,
		data,
	};
}

export function updateErrorForDocuments( query, data, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_DOCUMENTS,
		query,
		data,
		error,
	};
}

export function updateDocumentsSummary( query, data ) {
	return {
		type: TYPES.SET_DOCUMENTS_SUMMARY,
		query,
		data,
	};
}

export function updateErrorForDocumentsSummary( query, data, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_DOCUMENTS_SUMMARY,
		query,
		data,
		error,
	};
}
