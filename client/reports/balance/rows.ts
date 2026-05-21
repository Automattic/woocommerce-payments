/** @format */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type {
	ReportsBalanceSummary,
	ReportsBalanceSummaryRow,
} from 'wcpay/data/reports/hooks';
import { getAdminUrl } from 'wcpay/utils';

export type BalanceRowKey =
	| 'starting_balance'
	| 'total_charges_captured'
	| 'fees'
	| 'charge_fees'
	| 'payout_fees'
	| 'reader_fees'
	| 'dispute_fees'
	| 'fee_refunds'
	| 'refunds'
	| 'refund_failure'
	| 'disputes'
	| 'financing_payout'
	| 'financing_paydown'
	| 'network_costs'
	| 'other_adjustments'
	| 'net_balance_change_in_the_period'
	| 'payouts'
	| 'ending_balance';

export interface BalancePeriod {
	start: string;
	end: string;
}

export interface BalanceRow {
	key: BalanceRowKey;
	label: string;
	indent?: boolean;
	alwaysVisible?: boolean;
	getAmount: ( summary: ReportsBalanceSummary ) => number;
	getCount?: ( summary: ReportsBalanceSummary ) => number | undefined;
	exploreLink?: (
		summary: ReportsBalanceSummary,
		period: BalancePeriod
	) => string;
}

const toDateFilterValue = ( value: string ): string => value.slice( 0, 10 );

const getDateBetween = ( period: BalancePeriod ): string[] => [
	toDateFilterValue( period.start ),
	toDateFilterValue( period.end ),
];

const getRow = (
	summary: ReportsBalanceSummary,
	key: BalanceRowKey
): ReportsBalanceSummaryRow | undefined =>
	summary[ key ] as ReportsBalanceSummaryRow | undefined;

const getAmount =
	( key: BalanceRowKey ) =>
	( summary: ReportsBalanceSummary ): number =>
		getRow( summary, key )?.amount ?? 0;

const getCount =
	( key: BalanceRowKey ) =>
	( summary: ReportsBalanceSummary ): number | undefined =>
		getRow( summary, key )?.count;

const getExploreUrl =
	(
		path: string,
		query: Record< string, unknown > = {}
	): NonNullable< BalanceRow[ 'exploreLink' ] > =>
	( summary, period ) => {
		void summary;
		return getAdminUrl( {
			page: 'wc-admin',
			path,
			filter: 'advanced',
			date_between: getDateBetween( period ),
			...query,
		} );
	};

// The plan and tests intentionally pin this export name as the row-contract
// constant used by the Balance UI.
// eslint-disable-next-line @typescript-eslint/naming-convention
export const BALANCE_ROWS: BalanceRow[] = [
	{
		key: 'starting_balance',
		label: __( 'Starting balance', 'woocommerce-payments' ),
		alwaysVisible: true,
		getAmount: getAmount( 'starting_balance' ),
	},
	{
		key: 'total_charges_captured',
		label: __( 'Total charges captured', 'woocommerce-payments' ),
		alwaysVisible: true,
		getAmount: getAmount( 'total_charges_captured' ),
		getCount: getCount( 'total_charges_captured' ),
		exploreLink: getExploreUrl( '/payments/transactions', {
			type_is_in: [ 'charge', 'payment' ],
		} ),
	},
	{
		key: 'fees',
		label: __( 'Fees', 'woocommerce-payments' ),
		alwaysVisible: true,
		getAmount: getAmount( 'fees' ),
	},
	{
		key: 'charge_fees',
		label: __( 'Charge fees', 'woocommerce-payments' ),
		indent: true,
		getAmount: getAmount( 'charge_fees' ),
		exploreLink: getExploreUrl( '/payments/reports', {
			tab: 'fees',
			filter: undefined,
		} ),
	},
	{
		key: 'payout_fees',
		label: __( 'Payout fees', 'woocommerce-payments' ),
		indent: true,
		getAmount: getAmount( 'payout_fees' ),
	},
	{
		key: 'reader_fees',
		label: __( 'Reader fees', 'woocommerce-payments' ),
		indent: true,
		getAmount: getAmount( 'reader_fees' ),
	},
	{
		key: 'dispute_fees',
		label: __( 'Disputes fees', 'woocommerce-payments' ),
		indent: true,
		getAmount: getAmount( 'dispute_fees' ),
	},
	{
		key: 'fee_refunds',
		label: __( 'Fee refunds', 'woocommerce-payments' ),
		indent: true,
		getAmount: getAmount( 'fee_refunds' ),
	},
	{
		key: 'refunds',
		label: __( 'Refunds', 'woocommerce-payments' ),
		getAmount: getAmount( 'refunds' ),
		getCount: getCount( 'refunds' ),
		exploreLink: getExploreUrl( '/payments/transactions', {
			type_is_in: [ 'refund', 'payment_refund' ],
		} ),
	},
	{
		key: 'refund_failure',
		label: __( 'Refund failures', 'woocommerce-payments' ),
		getAmount: getAmount( 'refund_failure' ),
		getCount: getCount( 'refund_failure' ),
	},
	{
		key: 'disputes',
		label: __( 'Disputes', 'woocommerce-payments' ),
		getAmount: getAmount( 'disputes' ),
		getCount: getCount( 'disputes' ),
		exploreLink: getExploreUrl( '/payments/disputes' ),
	},
	{
		key: 'financing_payout',
		label: __( 'Financing payout', 'woocommerce-payments' ),
		getAmount: getAmount( 'financing_payout' ),
		getCount: getCount( 'financing_payout' ),
	},
	{
		key: 'financing_paydown',
		label: __( 'Financing paydown', 'woocommerce-payments' ),
		getAmount: getAmount( 'financing_paydown' ),
		getCount: getCount( 'financing_paydown' ),
	},
	{
		key: 'network_costs',
		label: __( 'Network costs', 'woocommerce-payments' ),
		getAmount: getAmount( 'network_costs' ),
		getCount: getCount( 'network_costs' ),
	},
	{
		key: 'other_adjustments',
		label: __( 'Other adjustments', 'woocommerce-payments' ),
		getAmount: getAmount( 'other_adjustments' ),
		getCount: getCount( 'other_adjustments' ),
	},
	{
		key: 'net_balance_change_in_the_period',
		label: __( 'Net balance change in the period', 'woocommerce-payments' ),
		alwaysVisible: true,
		getAmount: getAmount( 'net_balance_change_in_the_period' ),
	},
	{
		key: 'payouts',
		label: __( 'Payouts', 'woocommerce-payments' ),
		alwaysVisible: true,
		getAmount: getAmount( 'payouts' ),
		getCount: getCount( 'payouts' ),
		exploreLink: getExploreUrl( '/payments/payouts' ),
	},
	{
		key: 'ending_balance',
		label: __( 'Ending balance', 'woocommerce-payments' ),
		alwaysVisible: true,
		getAmount: getAmount( 'ending_balance' ),
	},
];

export const isAnchorRow = ( row: BalanceRow ): boolean =>
	row.alwaysVisible === true;

export const getVisibleBalanceRows = (
	summary: ReportsBalanceSummary
): BalanceRow[] =>
	BALANCE_ROWS.filter(
		( row ) =>
			row.alwaysVisible ||
			row.getAmount( summary ) !== 0 ||
			( row.getCount?.( summary ) ?? 0 ) !== 0
	);
