/** @format */

/**
 * External dependencies
 */
import { generateCSVDataFromTable } from '@woocommerce/csv-export';

/**
 * Internal dependencies
 */
import {
	formatExplicitCurrency,
	formatExportAmount,
} from 'multi-currency/interface/functions';
import type { ReportsPeriodRange } from 'wcpay/reports/period-selector';
import type { BalanceRow } from './rows';
import { getBalanceReportIdentity, getRowLabel } from './utils';

const formatYmdUTC = ( value: string ): string => value.slice( 0, 10 );

export const getBalanceExportFileName = (
	period: ReportsPeriodRange
): string =>
	`wcpay-balance-${ formatYmdUTC( period.start ) }_${ formatYmdUTC(
		period.end
	) }.csv`;

export const getBalanceCSV = ( {
	visibleRows,
	summary,
	displayPeriod,
	currency,
}: {
	visibleRows: BalanceRow[];
	summary: Parameters< BalanceRow[ 'getAmount' ] >[ 0 ];
	displayPeriod: ReportsPeriodRange;
	currency: string;
} ): string => {
	const periodStart = formatYmdUTC( displayPeriod.start );
	const periodEnd = formatYmdUTC( displayPeriod.end );
	const reportIdentity = getBalanceReportIdentity();

	return generateCSVDataFromTable(
		[
			{ key: 'business_name', label: 'business_name' },
			{
				key: 'woopayments_account_id',
				label: 'woopayments_account_id',
			},
			{ key: 'row_key', label: 'row_key' },
			{ key: 'label', label: 'label' },
			{ key: 'amount', label: 'amount' },
			{ key: 'count', label: 'count' },
			{ key: 'currency', label: 'currency' },
			{ key: 'period_start', label: 'period_start' },
			{ key: 'period_end', label: 'period_end' },
		],
		visibleRows.map( ( row ) => {
			const count = row.getCount?.( summary );
			const rowLabel = getRowLabel( row, displayPeriod );

			return [
				{
					value: reportIdentity.businessName,
					display: reportIdentity.businessName,
				},
				{
					value: reportIdentity.accountId,
					display: reportIdentity.accountId,
				},
				{ value: row.key, display: row.key },
				{ value: rowLabel, display: rowLabel },
				{
					// Export the raw amount, only rescaled from minor to major
					// units. Unlike the on-screen and print tables, the CSV does
					// not apply the `displayNegative` sign flip (e.g. Payouts) —
					// it deliberately carries each row's stored sign so the file
					// stays machine-readable. See the row contract in rows.ts.
					value: formatExportAmount(
						row.getAmount( summary ),
						currency
					),
					display: '',
				},
				{ value: count ?? '', display: '' },
				{ value: currency, display: currency },
				{ value: periodStart, display: periodStart },
				{ value: periodEnd, display: periodEnd },
			];
		} )
	);
};

/**
 * Formats a Balance summary amount in the standard WooPayments currency style,
 * with an explicit leading `+` on inflows — e.g. `+$1,234.00 USD` or
 * `-$80.00 USD`.
 *
 * `formatExplicitCurrency` already renders the leading `-` on outflows, so the
 * `+` on inflows is the only Balance-specific part: the report states the
 * direction of every movement rather than leaving it implied.
 *
 * Zero renders without a sign so reconciliation reports don't pin a misleading
 * positive or negative direction onto an empty line.
 */
export const formatBalanceAmount = (
	amount: number,
	currencyCode: string
): string => {
	const formatted = formatExplicitCurrency( amount, currencyCode );

	return amount > 0 ? `+${ formatted }` : formatted;
};
