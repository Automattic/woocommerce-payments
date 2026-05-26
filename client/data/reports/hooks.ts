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
import type { ReportsPeriodRange } from 'wcpay/reports/period-selector';

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

export interface ReportsBalanceSummaryRow {
	amount: number;
	count?: number;
}

export interface ReportsBalanceSummary {
	currency?: string;
	period?: Partial< ReportsPeriodRange >;
	starting_balance?: ReportsBalanceSummaryRow;
	total_charges_captured?: ReportsBalanceSummaryRow;
	fees?: ReportsBalanceSummaryRow;
	charge_fees?: ReportsBalanceSummaryRow;
	payout_fees?: ReportsBalanceSummaryRow;
	reader_fees?: ReportsBalanceSummaryRow;
	dispute_fees?: ReportsBalanceSummaryRow;
	fee_refunds?: ReportsBalanceSummaryRow;
	refunds?: ReportsBalanceSummaryRow;
	refund_failure?: ReportsBalanceSummaryRow;
	disputes?: ReportsBalanceSummaryRow;
	financing_payout?: ReportsBalanceSummaryRow;
	financing_paydown?: ReportsBalanceSummaryRow;
	network_costs?: ReportsBalanceSummaryRow;
	other_adjustments?: ReportsBalanceSummaryRow;
	net_balance_change_in_the_period?: ReportsBalanceSummaryRow;
	payouts?: ReportsBalanceSummaryRow;
	ending_balance?: ReportsBalanceSummaryRow;
}

interface ReportsBalanceSummaryResult {
	summary: ReportsBalanceSummary;
	error?: Record< string, unknown >;
	isLoading: boolean;
}

const emptyReportsBalanceSummary: ReportsBalanceSummary = {};
const emptyReportsBalanceSummaryError: Record< string, unknown > = {};
const skippedReportsBalanceSummaryResult: ReportsBalanceSummaryResult = {
	summary: emptyReportsBalanceSummary,
	error: emptyReportsBalanceSummaryError,
	isLoading: false,
};

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

export const useReportsBalanceSummary = (
	period?: ReportsPeriodRange,
	currency = wcpaySettings.accountDefaultCurrency || ''
): ReportsBalanceSummaryResult => {
	return useSelect(
		( select ) => {
			if ( ! period ) {
				return skippedReportsBalanceSummaryResult;
			}

			const {
				getReportsBalanceSummary,
				getReportsBalanceSummaryError,
				isResolving,
			} = select( STORE_NAME );

			const query = {
				dateStart: period.start,
				dateEnd: period.end,
				currency: currency.toLowerCase(),
			};

			return {
				summary: getReportsBalanceSummary( query ),
				error: getReportsBalanceSummaryError( query ),
				isLoading: isResolving( 'getReportsBalanceSummary', [ query ] ),
			};
		},
		[ period?.start, period?.end, currency ]
	);
};

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
