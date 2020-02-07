/** @format */

/**
 * Internal dependencies
 */
import { getResourceId } from '../util';

/**
 * Retrieves the transactions state from the wp.data store if the state
 * has been initialized, otherwise returns an empty state.
 *
 * @param {object} state Current wp.data state.
 *
 * @returns {object} The transactions state.
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
 * @param {object} state Current wp.data state.
 * @param {object} query The transactions query.
 *
 * @returns {object} The list of transactions for the given query.
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
 * @param {object} state Current wp.data state.
 * @param {object} query The transactions summary query.
 *
 * @returns {object} The transaction summary for the given query.
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
