/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

// eslint-disable-next-line camelcase
export const useTransactions = ( { paged = '1', per_page = '25' } ) => useSelect( select => {
	const { getTransactions, getTransactionsError, isResolving } = select( STORE_NAME );

	// eslint-disable-next-line camelcase
	const query = { paged, perPage: per_page };
	return {
		transactions: getTransactions( query ),
		transactionsError: getTransactionsError( query ),
		isLoading: isResolving( 'getTransactions', [ query ] ),
	};
// eslint-disable-next-line camelcase
}, [ { paged, per_page } ] );

export const useTransactionsSummary = () => useSelect( select => {
	const {
		getTransactionsSummary,
		isResolving,
	} = select( STORE_NAME );

	return {
		transactionsSummary: getTransactionsSummary(),
		isLoading: isResolving( 'getTransactionsSummary' ),
	};
} );
