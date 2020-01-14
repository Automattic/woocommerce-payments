/** @format */

export const getTransactions = ( state ) => {
	return state.transactions.data || [];
};

export const getTransactionsError = ( state ) => {
	return state.transactions.error || {};
};
