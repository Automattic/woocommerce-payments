/** @format */

export const getTransactionsForPage = ( state, page = 1 ) => {
	const transactionIds = state.pages && state.pages[ page ] ? state.pages[ page ] : [];
	return transactionIds.map( id => state.transactions[ id ] );
};
