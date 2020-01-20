/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useTransactions = () => useSelect( select => {
	const { getTransactions, getTransactionsError, isResolving } = select( STORE_NAME );
	return {
		transactions: getTransactions(),
		transactionsError: getTransactionsError(),
		isLoading: isResolving( 'getTransactions' ),
	};
} );

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
