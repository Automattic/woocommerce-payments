/** @format */

/**
 * Internal dependencies
 */
import { getResourceId } from 'utils/data';

/**
 * Retrieves the transactions state from the wp.data store if the state
 * has been initialized, otherwise returns an empty state.
 *
 * @param {Object} state Current wp.data state.
 *
 * @return {Object} The transactions state.
 */
const getTransactionsState = ( state ) => {
	if ( ! state ) {
		return {};
	}

	return state.transactions || {};
};

/**
 * Retrieves the transactions corresponding to the provided query or a sane
 * default if they don't exist.
 *
 * @param {Object} state Current wp.data state.
 * @param {Object} query The transactions query.
 *
 * @return {Object} The list of transactions for the given query.
 */
const getTransactionsForQuery = ( state, query ) => {
	const index = getResourceId( query );
	return getTransactionsState( state )[ index ] || {};
};

export const getTransactions = ( state, query ) => {
	return getTransactionsForQuery( state, query ).data || [];
};

export const getTransactionsError = ( state, query ) => {
	return getTransactionsForQuery( state, query ).error || {};
};

/**
 * Retrieves the transaction summary corresponding to the provided query.
 *
 * @param {Object} state Current wp.data state.
 * @param {Object} query The transactions summary query.
 *
 * @return {Object} The transaction summary for the given query.
 */
const getTransactionsSummaryForQuery = ( state, query ) => {
	const index = getResourceId( query );
	return getTransactionsState( state ).summary[ index ] || {};
};

export const getTransactionsSummary = ( state, query ) => {
	return getTransactionsSummaryForQuery( state, query ).data || {};
};

export const getTransactionsSummaryError = ( state, query ) => {
	return getTransactionsSummaryForQuery( state, query ).error || {};
};
