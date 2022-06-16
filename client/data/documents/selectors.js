/** @format */

/**
 * Internal dependencies
 */
import { getResourceId } from 'utils/data';

/**
 * Retrieves the documents state from the wp.data store if the state
 * has been initialized, otherwise returns an empty state.
 *
 * @param {Object} state Current wp.data state.
 *
 * @return {Object} The documents state.
 */
const getDocumentsState = ( state ) => {
	if ( ! state ) {
		return {};
	}

	return state.documents || {};
};

/**
 * Retrieves the documents corresponding to the provided query or a sane
 * default if they don't exist.
 *
 * @param {Object} state Current wp.data state.
 * @param {Object} query The documents query.
 *
 * @return {Object} The list of documents for the given query.
 */
const getDocumentsForQuery = ( state, query ) => {
	const index = getResourceId( query );
	return getDocumentsState( state )[ index ] || {};
};

export const getDocuments = ( state, query ) => {
	return getDocumentsForQuery( state, query ).data || [];
};

export const getDocumentsError = ( state, query ) => {
	return getDocumentsForQuery( state, query ).error || {};
};

/**
 * Retrieves the document summary corresponding to the provided query.
 *
 * @param {Object} state Current wp.data state.
 * @param {Object} query The documents summary query.
 *
 * @return {Object} The document summary for the given query.
 */
const getDocumentsSummaryForQuery = ( state, query ) => {
	const index = getResourceId( query );
	return getDocumentsState( state ).summary[ index ] || {};
};

export const getDocumentsSummary = ( state, query ) => {
	return getDocumentsSummaryForQuery( state, query ).data || {};
};

export const getDocumentsSummaryError = ( state, query ) => {
	return getDocumentsSummaryForQuery( state, query ).error || {};
};
