/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

// eslint-disable-next-line camelcase
export const useTransactions = ( { paged = '1', per_page: perPage = '25' }, depositId = null ) => useSelect( select => {
	const { getTransactions, getTransactionsError, isResolving } = select( STORE_NAME );

	const query = { paged, perPage, depositId };
	return {
		transactions: getTransactions( query ),
		transactionsError: getTransactionsError( query ),
		isLoading: isResolving( 'getTransactions', [ query ] ),
	};
}, [ paged, perPage, depositId ] );

export const useTransactionsSummary = ( depositId = null ) => useSelect( select => {
	const {
		getTransactionsSummary,
		isResolving,
	} = select( STORE_NAME );

	const query = { depositId };
	return {
		transactionsSummary: getTransactionsSummary( query ),
		isLoading: isResolving( 'getTransactionsSummary', [ query ] ),
	};
}, [ depositId ] );
