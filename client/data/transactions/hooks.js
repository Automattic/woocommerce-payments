/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import moment from 'moment';

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
		match,
		date_before: dateBefore,
		date_after: dateAfter,
		date_between: dateBetween,
		type_is: typeIs,
		type_is_not: typeIsNot,
		store_currency_is: storeCurrencyIs,
		search,
	},
	depositId
) =>
	useSelect(
		( select ) => {
			const {
				getTransactions,
				getTransactionsError,
				isResolving,
			} = select( STORE_NAME );

			const query = {
				paged: Number.isNaN( parseInt( paged, 10 ) ) ? '1' : paged,
				perPage: Number.isNaN( parseInt( perPage, 10 ) )
					? '25'
					: perPage,
				orderby: orderby || 'date',
				order: order || 'desc',
				match,
				dateBefore,
				dateAfter,
				dateBetween:
					dateBetween &&
					dateBetween.sort( ( a, b ) =>
						moment( a ).diff( moment( b ) )
					),
				typeIs,
				typeIsNot,
				storeCurrencyIs,
				depositId,
				search,
			};

			return {
				transactions: getTransactions( query ),
				transactionsError: getTransactionsError( query ),
				isLoading: isResolving( 'getTransactions', [ query ] ),
			};
		},
		[
			paged,
			perPage,
			orderby,
			order,
			match,
			dateBefore,
			dateAfter,
			dateBetween,
			typeIs,
			typeIsNot,
			storeCurrencyIs,
			depositId,
			search,
		]
	);

export const useTransactionsSummary = (
	{
		match,
		date_before: dateBefore,
		date_after: dateAfter,
		date_between: dateBetween,
		type_is: typeIs,
		type_is_not: typeIsNot,
		store_currency_is: storeCurrencyIs,
		search,
	},
	depositId
) =>
	useSelect(
		( select ) => {
			const { getTransactionsSummary, isResolving } = select(
				STORE_NAME
			);

			const query = {
				match,
				dateBefore,
				dateAfter,
				dateBetween,
				typeIs,
				typeIsNot,
				storeCurrencyIs,
				depositId,
				search,
			};

			return {
				transactionsSummary: getTransactionsSummary( query ),
				isLoading: isResolving( 'getTransactionsSummary', [ query ] ),
			};
		},
		[
			match,
			dateBefore,
			dateAfter,
			dateBetween,
			typeIs,
			typeIsNot,
			storeCurrencyIs,
			depositId,
			search,
		]
	);
