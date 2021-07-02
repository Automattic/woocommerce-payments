/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import moment from 'moment';
import type { query } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';

// TODO: refine this type with more detailed information.
export type transaction = {
	amount: number;
	order: {
		subscriptions?: { number: number; url: string }[];
		url?: string;
		customer_url?: string;
		number?: number;
	};
	charge_id: string;
	fees: number;
	net: number;
	risk_level: number;
	customer_amount: number;
	customer_name: string;
	customer_email: string;
	customer_country: string;
	customer_currency: string;
	deposit_id?: string;
	available_on: string;
	currency: string;
	transaction_id: string;
	date: string;
	type: 'charge' | 'refund';
	source: string;
};

type transactions = {
	transactions: transaction[];
	transactionsError: unknown;
	isLoading: boolean;
};
type transactionsSummary = {
	transactionsSummary: {
		count?: number;
		total?: number;
		fees?: number;
		net?: number;
		currency?: string;
		store_currencies?: string[];
	};
	isLoading: boolean;
};

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
	}: query,
	depositId: string
): transactions =>
	useSelect(
		( select ) => {
			const {
				getTransactions,
				getTransactionsError,
				isResolving,
			} = select( STORE_NAME );

			const newQuery = {
				paged: Number.isNaN( parseInt( paged ?? '', 10 ) )
					? '1'
					: paged,
				perPage: Number.isNaN( parseInt( perPage ?? '', 10 ) )
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
				transactions: getTransactions( newQuery ),
				transactionsError: getTransactionsError( newQuery ),
				isLoading: isResolving( 'getTransactions', [ newQuery ] ),
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
			JSON.stringify( dateBetween ),
			typeIs,
			typeIsNot,
			storeCurrencyIs,
			depositId,
			JSON.stringify( search ),
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
	}: query,
	depositId: string
): transactionsSummary =>
	useSelect(
		( select ) => {
			const { getTransactionsSummary, isResolving } = select(
				STORE_NAME
			);

			const newQuery = {
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
				transactionsSummary: getTransactionsSummary( newQuery ),
				isLoading: isResolving( 'getTransactionsSummary', [
					newQuery,
				] ),
			};
		},
		[
			match,
			dateBefore,
			dateAfter,
			JSON.stringify( dateBetween ),
			typeIs,
			typeIsNot,
			storeCurrencyIs,
			depositId,
			JSON.stringify( search ),
		]
	);
