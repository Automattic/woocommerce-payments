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

export const getTransactions = ( state, { paged = '1', perPage = '25' } ) => {
	return state.transactions[ getResourceId( ID_PREFIX.transactions, { paged, perPage } ) ] || [];
};

export const getTransactionsError = ( state ) => {
	return state.transactions.error || {};
};

export const getTransactionsSummary = ( state ) => {
	const summary = getTransactionsState( state ).summary || {};
	return summary.data || {};
};

export const getTransactionsSummaryError = ( state ) => {
	const summary = getTransactionsState( state ).summary || {};
	return summary.error || {};
};
