/** @format */
/* eslint-disable react-hooks/exhaustive-deps -- useSelect dep arrays intentionally use JSON.stringify for object comparison */

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

export type ReportsFeeType =
	| 'charge'
	| 'payment'
	| 'payment_failure_refund'
	| 'payment_refund'
	| 'refund'
	| 'refund_failure'
	| 'dispute'
	| 'dispute_reversal'
	| 'fee_refund'
	| 'network_costs';

export interface ReportsFee {
	transaction_id: string;
	date: string;
	payment_id?: string;
	channel?: string;
	payment_method?: {
		type?: string;
	};
	type: ReportsFeeType;
	transaction_currency: string;
	amount: number;
	exchange_rate?: number;
	deposit_currency: string;
	fees: number;
	net_amount?: number;
	order_id?: number | string | null;
	risk_level?: number;
	deposit_date?: string | null;
	deposit_id?: string | null;
	deposit_status?: string | null;
}

interface ReportsFees {
	feesRows: ReportsFee[];
	feesError?: Record< string, unknown >;
	isLoading: boolean;
}

interface ReportsFeesSummary {
	feesSummary: {
		count?: number;
		total?: number;
		fees?: number;
		net?: number;
		currency?: string;
		store_currencies?: string[];
		customer_currencies?: string[];
		sources?: string[];
		types?: ReportsFeeType[];
	};
	feesSummaryError?: Record< string, unknown >;
	isLoading: boolean;
}

interface ReportsFeesQuery extends Query {
	payment_method_type?: string;
	type?: string;
	order_id?: string;
	deposit_id?: string;
	customer_email?: string;
}

const sortDateBetween = ( dateBetween: Query[ 'date_between' ] ) =>
	Array.isArray( dateBetween )
		? [ ...dateBetween ].sort( ( a, b ) => moment( a ).diff( moment( b ) ) )
		: dateBetween;

export const useReportsFees = ( {
	paged,
	per_page: perPage,
	orderby,
	order,
	match,
	date_before: dateBefore,
	date_after: dateAfter,
	date_between: dateBetween,
	payment_method_type: paymentMethodType,
	type,
	order_id: orderId,
	deposit_id: depositId,
	customer_email: customerEmail,
	search,
}: ReportsFeesQuery ): ReportsFees =>
	useSelect(
		( select ) => {
			const { getReportsFees, getReportsFeesError, isResolving } =
				select( STORE_NAME );

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
				dateBetween: sortDateBetween( dateBetween ),
				paymentMethodType,
				type,
				orderId,
				depositId,
				customerEmail,
				search,
			};

			return {
				feesRows: getReportsFees( query ),
				feesError: getReportsFeesError( query ),
				isLoading: isResolving( 'getReportsFees', [ query ] ),
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
			paymentMethodType,
			JSON.stringify( type ),
			orderId,
			depositId,
			customerEmail,
			JSON.stringify( search ),
		]
	);

export const useReportsFeesSummary = ( {
	match,
	date_before: dateBefore,
	date_after: dateAfter,
	date_between: dateBetween,
	payment_method_type: paymentMethodType,
	type,
	order_id: orderId,
	deposit_id: depositId,
	customer_email: customerEmail,
	search,
}: ReportsFeesQuery ): ReportsFeesSummary =>
	useSelect(
		( select ) => {
			const {
				getReportsFeesSummary,
				getReportsFeesSummaryError,
				isResolving,
			} = select( STORE_NAME );

			const query = {
				match,
				dateBefore,
				dateAfter,
				dateBetween: sortDateBetween( dateBetween ),
				paymentMethodType,
				type,
				orderId,
				depositId,
				customerEmail,
				search,
			};

			return {
				feesSummary: getReportsFeesSummary( query ),
				feesSummaryError: getReportsFeesSummaryError( query ),
				isLoading: isResolving( 'getReportsFeesSummary', [ query ] ),
			};
		},
		[
			match,
			dateBefore,
			dateAfter,
			JSON.stringify( dateBetween ),
			paymentMethodType,
			JSON.stringify( type ),
			orderId,
			depositId,
			customerEmail,
			JSON.stringify( search ),
		]
	);
