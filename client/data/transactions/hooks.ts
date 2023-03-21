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
	order: OrderDetails;
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
	channel: 'in_person' | 'online';
	// A field to identify the payment's source.
	// Usually last 4 digits for card payments, bank name for bank transfers...
	source_identifier: string;
	source:
		| 'ach_credit_transfer'
		| 'ach_debit'
		| 'acss_debit'
		| 'alipay'
		| 'au_becs_debit'
		| 'bancontact'
		| 'eps'
		| 'giropay'
		| 'sepa_debit'
		| 'ideal'
		| 'klarna'
		| 'multibanco'
		| 'p24'
		| 'sofort'
		| 'stripe_account'
		| 'wechat'
		| 'amex'
		| 'diners'
		| 'discover'
		| 'jcb'
		| 'mastercard'
		| 'unionpay'
		| 'visa'
		| 'link';
	loan_id?: string;
	metadata?: {
		charge_type: 'card_reader_fee';
		interval_from: string;
		interval_to: string;
	};
	payment_intent_id?: string;
}

interface Transactions {
	transactions: Transaction[];
	transactionsError?: string;
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
		customer_currencies?: string[];
	};
	isLoading: boolean;
}

export interface FraudOutcomeTransaction {
	amount: number;
	created: string;
	currency: string;
	customer_name: string;
	order_id: number;
	payment_intent: {
		id: string;
		status: string;
	};
	status: 'allow' | 'review' | 'block';
}

interface FraudOutcomeTransactions {
	transactions: FraudOutcomeTransaction[];
	transactionsError?: string;
	isLoading: boolean;
}

interface FraudOutcomeTransactionsSummary {
	transactionsSummary: {
		count?: number;
		total?: number;
		currencies?: string[];
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
		customer_currency_is: customerCurrencyIs,
		customer_currency_is_not: customerCurrencyIsNot,
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
				customerCurrencyIs,
				customerCurrencyIsNot,
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
			customerCurrencyIs,
			customerCurrencyIsNot,
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
		customer_currency_is: customerCurrencyIs,
		customer_currency_is_not: customerCurrencyIsNot,
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
				customerCurrencyIs,
				customerCurrencyIsNot,
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
			customerCurrencyIs,
			customerCurrencyIsNot,
			loanIdIs,
			depositId,
			JSON.stringify( search ),
		]
	);

export const useFraudOutcomeTransactions = (
	status: string,
	{ paged, per_page: perPage, orderby, order, search }: Query
): FraudOutcomeTransactions =>
	useSelect(
		( select ) => {
			const {
				getFraudOutcomeTransactions,
				getFraudOutcomeTransactionsError,
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
				search,
			};

			return {
				transactions: getFraudOutcomeTransactions( status, query ),
				transactionsError: getFraudOutcomeTransactionsError(
					status,
					query
				),
				isLoading: isResolving( 'getFraudOutcomeTransactions', [
					status,
					query,
				] ),
			};
		},
		[ paged, perPage, orderby, order, JSON.stringify( search ) ]
	);

export const useFraudOutcomeTransactionsSummary = (
	status: string,
	{ search }: Query
): FraudOutcomeTransactionsSummary =>
	useSelect(
		( select ) => {
			const {
				getFraudOutcomeTransactionsSummary,
				getFraudOutcomeTransactionsSummaryError,
				isResolving,
			} = select( STORE_NAME );

			const query = { search };

			return {
				transactionsSummary: getFraudOutcomeTransactionsSummary(
					status,
					query
				),
				transactionsSummaryError: getFraudOutcomeTransactionsSummaryError(
					status,
					query
				),
				isLoading: isResolving( 'getFraudOutcomeTransactionsSummary', [
					status,
					query,
				] ),
			};
		},
		[ status, JSON.stringify( search ) ]
	);
