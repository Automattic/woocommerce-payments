/** @format */

/**
 * Internal dependencies
 */
import { getResourceId } from '../util';
import { ID_PREFIX } from '../constants';

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
	const index = getResourceId( ID_PREFIX.transactions, query );
	return state.transactions[ index ] || {};
};

export const getTransactions = ( state, { paged = '1', perPage = '25' } ) => {
	return getTransactionsForQuery( state, { paged, perPage } ).data || [];
};

export const getTransactionsError = ( state, { paged = '1', perPage = '25' } ) => {
	return getTransactionsForQuery( state, { paged, perPage } ).error || {};
};

export const getTransactionsSummary = ( state ) => {
	const summary = getTransactionsState( state ).summary || {};
	return summary.data || {};
};

export const getTransactionsSummaryError = ( state ) => {
	const summary = getTransactionsState( state ).summary || {};
	return summary.error || {};
};
