/** @format */

/**
 * Internal dependencies
 */
import { getResourceId } from 'utils/data';

const EMPTY_OBJECT = {};
const EMPTY_ARRAY = [];

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
	return getTransactionsForQuery( state, query ).data || EMPTY_ARRAY;
};

export const getTransactionsError = ( state, query ) => {
	return getTransactionsForQuery( state, query ).error || EMPTY_OBJECT;
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
	return getTransactionsSummaryForQuery( state, query ).data || EMPTY_OBJECT;
};

export const getTransactionsSummaryError = ( state, query ) => {
	return getTransactionsSummaryForQuery( state, query ).error || EMPTY_OBJECT;
};

const getFraudOutcomeTransactionsForQuery = ( state, status, query ) => {
	const index = getResourceId( query );
	return (
		getTransactionsState( state ).fraudProtection?.[ status ]?.[ index ] ||
		{}
	);
};

export const getFraudOutcomeTransactions = ( state, status, query ) => {
	return (
		getFraudOutcomeTransactionsForQuery( state, status, query ).data ||
		EMPTY_ARRAY
	);
};

export const getFraudOutcomeTransactionsError = ( state, status, query ) => {
	return (
		getFraudOutcomeTransactionsForQuery( state, status, query ).error ||
		EMPTY_OBJECT
	);
};

const getFraudOutcomeTransactionsSummaryForQuery = ( state, status, query ) => {
	const index = getResourceId( query );
	return (
		getTransactionsState( state ).fraudProtection?.[ status ]?.summary[
			index
		] || {}
	);
};

export const getFraudOutcomeTransactionsSummary = ( state, status, query ) => {
	return (
		getFraudOutcomeTransactionsSummaryForQuery( state, status, query )
			.data || EMPTY_OBJECT
	);
};

export const getFraudOutcomeTransactionsSummaryError = (
	state,
	status,
	query
) => {
	return (
		getFraudOutcomeTransactionsSummaryForQuery( state, status, query )
			.error || EMPTY_OBJECT
	);
};
