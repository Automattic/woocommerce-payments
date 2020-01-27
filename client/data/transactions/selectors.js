/** @format */

const getTransactionsState = ( state ) => {
	if ( ! state ) {
		return {};
	}

	return state.transactions || {};
};

export const getTransactions = ( state ) => {
	return state.transactions.data || [];
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
