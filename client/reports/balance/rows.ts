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

/**
 * Indent depth for a Balance summary row.
 *
 * `0` — top-level totals (Starting balance, Total charges captured, Net balance
 * change, Ending balance).
 * `1` — group rows (Fees, Refunds, Disputes, Payouts).
 * `2` — sub-rows nested inside a group (Charge fees, Dispute fees, etc.).
 */
export type BalanceRowDepth = 0 | 1 | 2;

export interface BalanceRow {
	key: BalanceRowKey;
	label: string;
	depth?: BalanceRowDepth;
	alwaysVisible?: boolean;
	/**
	 * Force the displayed amount to render with a leading minus sign even when
	 * the raw value is positive. Used for rows that always represent an outflow
	 * (e.g. Payouts), so the visual sign communicates direction-of-flow rather
	 * than the storage sign of the underlying datum. Zero amounts are still
	 * rendered without a sign. The CSV export uses the raw value.
	 */
	displayNegative?: boolean;
	getAmount: ( summary: ReportsBalanceSummary ) => number;
	getCount?: ( summary: ReportsBalanceSummary ) => number | undefined;
}

export const getRowDepth = ( row: BalanceRow ): BalanceRowDepth =>
	row.depth ?? 0;

/**
 * Applies the row-level `displayNegative` contract to a raw amount: outflow
 * rows (e.g. Payouts) render with a leading minus even when the underlying
 * datum is stored as a positive magnitude. Zero amounts pass through unchanged
 * so reconciliation reports don't pin a misleading sign onto an empty line.
 * The CSV export uses the raw value — only the on-screen and print tables
 * apply this transform.
 */
export const getDisplayedAmount = (
	row: BalanceRow,
	amount: number
): number => {
	if ( row.displayNegative && amount !== 0 ) {
		return -Math.abs( amount );
	}

	return amount;
};

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
	},
	{
		key: 'fees',
		label: __( 'Fees', 'woocommerce-payments' ),
		alwaysVisible: true,
		depth: 1,
		getAmount: getAmount( 'fees' ),
	},
	{
		key: 'charge_fees',
		label: __( 'Charge fees', 'woocommerce-payments' ),
		depth: 2,
		getAmount: getAmount( 'charge_fees' ),
	},
	{
		key: 'dispute_fees',
		label: __( 'Dispute fees', 'woocommerce-payments' ),
		depth: 2,
		getAmount: getAmount( 'dispute_fees' ),
	},
	{
		key: 'fee_refunds',
		label: __( 'Fee refunds', 'woocommerce-payments' ),
		depth: 2,
		getAmount: getAmount( 'fee_refunds' ),
	},
	{
		key: 'refunds',
		label: __( 'Refunds', 'woocommerce-payments' ),
		depth: 1,
		getAmount: getAmount( 'refunds' ),
		getCount: getCount( 'refunds' ),
	},
	{
		key: 'refund_failure',
		label: __( 'Refund failures', 'woocommerce-payments' ),
		depth: 1,
		getAmount: getAmount( 'refund_failure' ),
		getCount: getCount( 'refund_failure' ),
	},
	{
		key: 'disputes',
		label: __( 'Disputes', 'woocommerce-payments' ),
		depth: 1,
		getAmount: getAmount( 'disputes' ),
		getCount: getCount( 'disputes' ),
	},
	{
		key: 'financing_payout',
		label: __( 'Financing payout', 'woocommerce-payments' ),
		depth: 1,
		getAmount: getAmount( 'financing_payout' ),
		getCount: getCount( 'financing_payout' ),
	},
	{
		key: 'financing_paydown',
		label: __( 'Financing paydown', 'woocommerce-payments' ),
		depth: 1,
		getAmount: getAmount( 'financing_paydown' ),
		getCount: getCount( 'financing_paydown' ),
	},
	{
		key: 'payout_fees',
		label: __( 'Payout fees', 'woocommerce-payments' ),
		depth: 1,
		getAmount: getAmount( 'payout_fees' ),
	},
	{
		key: 'reader_fees',
		// "Reader costs" is the intentional UI label; `reader_fees` is the upstream API key.
		label: __( 'Reader costs', 'woocommerce-payments' ),
		depth: 1,
		getAmount: getAmount( 'reader_fees' ),
	},
	{
		key: 'network_costs',
		label: __( 'Network costs', 'woocommerce-payments' ),
		depth: 1,
		getAmount: getAmount( 'network_costs' ),
		getCount: getCount( 'network_costs' ),
	},
	{
		key: 'other_adjustments',
		label: __( 'Other adjustments', 'woocommerce-payments' ),
		depth: 1,
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
		depth: 1,
		displayNegative: true,
		getAmount: getAmount( 'payouts' ),
		getCount: getCount( 'payouts' ),
	},
	{
		key: 'ending_balance',
		label: __( 'Ending balance', 'woocommerce-payments' ),
		alwaysVisible: true,
		getAmount: getAmount( 'ending_balance' ),
	},
];

export const getVisibleBalanceRows = (
	summary: ReportsBalanceSummary
): BalanceRow[] =>
	BALANCE_ROWS.filter(
		( row ) =>
			row.alwaysVisible ||
			row.getAmount( summary ) !== 0 ||
			( row.getCount?.( summary ) ?? 0 ) !== 0
	);
