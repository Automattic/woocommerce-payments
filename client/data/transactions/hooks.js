/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';

export const useTransactions = (
	{
		paged,
		per_page: perPage,
		orderby,
		order,
		date_before: dateBefore,
		date_after: dateAfter,
		date_between: dateBetween,
		type_is: typeIs,
		type_is_not: typeIsNot,
	},
	depositId
) => useSelect( select => {
	const { getTransactions, getTransactionsError, isResolving } = select( STORE_NAME );

	const query = {
		paged: Number.isNaN( parseInt( paged, 10 ) ) ? '1' : paged,
		perPage: Number.isNaN( parseInt( perPage, 10 ) ) ? '25' : perPage,
		orderby: orderby || 'date',
		order: order || 'desc',
		dateBefore,
		dateAfter,
		dateBetween,
		typeIs,
		typeIsNot,
		depositId,
	};

	return {
		transactions: getTransactions( query ),
		transactionsError: getTransactionsError( query ),
		isLoading: isResolving( 'getTransactions', [ query ] ),
	};
}, [ paged, perPage, orderby, order, dateBefore, dateAfter, dateBetween, typeIs, typeIsNot, depositId ] );

export const useTransactionsSummary = (
	{
		date_before: dateBefore,
		date_after: dateAfter,
		date_between: dateBetween,
		type_is: typeIs,
		type_is_not: typeIsNot,
	},
	depositId
) => useSelect( select => {
	const { getTransactionsSummary,	isResolving } = select( STORE_NAME );

	const query = {
		dateBefore,
		dateAfter,
		dateBetween,
		typeIs,
		typeIsNot,
		depositId,
	};

	return {
		transactionsSummary: getTransactionsSummary( query ),
		isLoading: isResolving( 'getTransactionsSummary', [ query ] ),
	};
}, [ dateBefore, dateAfter, dateBetween, typeIs, typeIsNot, depositId ] );
