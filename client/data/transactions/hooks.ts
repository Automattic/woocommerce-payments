/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import moment from 'moment';
import type { Query } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';

// TODO: refine this type with more detailed information.
export interface Transaction {
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
	deposit_status?:
		| 'paid'
		| 'pending'
		| 'in_transit'
		| 'canceled'
		| 'failed'
		| 'estimated';
	available_on: string;
	currency: string;
	transaction_id: string;
	date: string;
	type: 'charge' | 'refund' | 'financing_payout' | 'financing_paydown';
	source: string;
	loan_id?: string;
	metadata?: {
		charge_type: 'card_reader_fee';
		interval_from: string;
		interval_to: string;
	};
}

interface Transactions {
	transactions: Transaction[];
	transactionsError: unknown;
	isLoading: boolean;
}
interface TransactionsSummary {
	transactionsSummary: {
		count?: number;
		total?: number;
		fees?: number;
		net?: number;
		currency?: string;
		store_currencies?: string[];
	};
	isLoading: boolean;
}

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
		loan_id_is: loanIdIs,
		search,
	}: Query,
	depositId: string
): Transactions =>
	useSelect(
		( select ) => {
			const {
				getTransactions,
				getTransactionsError,
				isResolving,
			} = select( STORE_NAME );

			const query = {
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
				loanIdIs,
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
			JSON.stringify( dateBetween ),
			typeIs,
			typeIsNot,
			storeCurrencyIs,
			loanIdIs,
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
		loan_id_is: loanIdIs,
		search,
	}: Query,
	depositId: string
): TransactionsSummary =>
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
				loanIdIs,
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
			JSON.stringify( dateBetween ),
			typeIs,
			typeIsNot,
			storeCurrencyIs,
			loanIdIs,
			depositId,
			JSON.stringify( search ),
		]
	);
