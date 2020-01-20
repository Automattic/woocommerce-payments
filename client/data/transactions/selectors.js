/** @format */

export const getTransactions = ( state ) => {
	return state.transactions.data || [];
};

export const getTransactionsError = ( state ) => {
	return state.transactions.error || {};
};

export const getTransactionsSummary = ( state ) => {
	return state.transactions.summary.data || {};
};

export const getTransactionsSummaryError = ( state ) => {
	return state.transactions.summary.error || {};
};
