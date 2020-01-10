/** @format */

export const getTransactionsPage = ( state, page = 1 ) => {
	const transactionIds = state.pages ? state.pages[ page ] || [] : [];
	if ( transactionIds.length === 0 ) {
		return [];
	}
	const transactions = transactionIds.map( id => state.transactions[ id ] );
	return transactions;
};
