/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

// eslint-disable-next-line camelcase
export const useTransactions = ( { paged, per_page: perPage }, depositId ) => useSelect( select => {
	const { getTransactions, getTransactionsError, isResolving } = select( STORE_NAME );

	const query = {
		paged: Number.isNaN( parseInt( paged, 10 ) ) ? '1' : paged,
		perPage: Number.isNaN( parseInt( perPage, 10 ) ) ? '25' : perPage,
		depositId: depositId || null,
	};
	return {
		transactions: getTransactions( query ),
		transactionsError: getTransactionsError( query ),
		isLoading: isResolving( 'getTransactions', [ query ] ),
	};
}, [ paged, perPage, depositId ] );

export const useTransactionsSummary = ( depositId ) => useSelect( select => {
	const {
		getTransactionsSummary,
		isResolving,
	} = select( STORE_NAME );

	const query = {
		depositId: depositId || null,
	};
	return {
		transactionsSummary: getTransactionsSummary( query ),
		isLoading: isResolving( 'getTransactionsSummary', [ query ] ),
	};
}, [ depositId ] );
