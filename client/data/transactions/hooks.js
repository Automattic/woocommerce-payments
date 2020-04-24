/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';
import { getFormattedQuery } from '../../util';

const paginationQueryMapping = {
	paged: { source: 'paged', default: 1, isNumber: true },
	perPage: { source: 'per_page', default: 25, isNumber: true },
	orderby: { source: 'orderby', default: 'date' },
	order: { source: 'order', default: 'desc' },
};
const filterQueryMapping = {
	date: { source: 'date', rules: [ 'before', 'after', 'between' ], isFilter: true },
	type: { source: 'type', rules: [ 'is', 'is_not' ], isFilter: true },
};

export const useTransactions = ( query, depositId ) => useSelect( select => {
	const { getTransactions, getTransactionsError, isResolving } = select( STORE_NAME );

	const formattedQuery = getFormattedQuery( query, { ...paginationQueryMapping, ...filterQueryMapping } );
	formattedQuery.depositId = depositId || null;

	return {
		transactions: getTransactions( formattedQuery ),
		transactionsError: getTransactionsError( formattedQuery ),
		isLoading: isResolving( 'getTransactions', [ formattedQuery ] ),
	};
}, [
	...Object.values( getFormattedQuery( query, { ...paginationQueryMapping, ...filterQueryMapping } ) ),
	depositId,
] );

export const useTransactionsSummary = ( query, depositId ) => useSelect( select => {
	const {
		getTransactionsSummary,
		isResolving,
	} = select( STORE_NAME );

	const formattedQuery = getFormattedQuery( query, filterQueryMapping );
	formattedQuery.depositId = depositId || null;
	return {
		transactionsSummary: getTransactionsSummary( formattedQuery ),
		isLoading: isResolving( 'getTransactionsSummary', [ formattedQuery ] ),
	};
}, [
	...Object.values( getFormattedQuery( query, filterQueryMapping ) ),
	depositId,
] );
