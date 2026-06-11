/** @format */

/**
 * External dependencies
 */
import { generateCSVDataFromTable } from '@woocommerce/csv-export';

/**
 * Internal dependencies
 */
import { formatExplicitCurrency } from 'multi-currency/interface/functions';
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
				{ value: row.getAmount( summary ), display: '' },
				{ value: count ?? '', display: '' },
				{ value: currency, display: currency },
				{ value: periodStart, display: periodStart },
				{ value: periodEnd, display: periodEnd },
			];
		} )
	);
};

/**
 * Formats a Balance summary amount in the "code-first with leading sign" style
 * used by the Balance report Figma design — e.g. `+USD 1,234.00` or `-USD 80.00`.
 *
 * The default `formatExplicitCurrency` output puts the symbol before the amount
 * and the ISO code after it (`$1,234.00 USD`). Balance rows show every amount
 * with an explicit sign and the ISO code in front, with no currency symbol, so
 * that running totals are easy to scan and reconciliation against bank
 * statements doesn't require eyeballing past a `$`.
 */
export const formatBalanceAmount = (
	amount: number,
	currencyCode: string
): string => {
	const upperCode = currencyCode.toUpperCase();
	const isNegative = amount < 0;
	const isZero = amount === 0;
	const absoluteAmount = isNegative ? -amount : amount;

	// `skipSymbol = true` removes the leading `$` (etc.) while keeping the
	// currency utility's locale-aware number formatting. Depending on explicit
	// pricing settings, the helper may also append a trailing ISO code.
	const formatted = formatExplicitCurrency( absoluteAmount, upperCode, true );

	// `formatExplicitCurrency` returns the value in one of two shapes depending
	// on whether explicit pricing is enabled in settings:
	//   • "1,234.00 USD"     — explicit pricing on (the common case)
	//   • "1,234.00"         — explicit pricing off
	// Move the ISO code to the front to match the Figma "code-first" pattern,
	// and drop any stray spaces left behind by the symbol removal.
	const withoutCode = formatted
		.replace( new RegExp( `\\s*${ upperCode }\\s*$` ), '' )
		.trim();

	// Zero balances render without a sign so reconciliation reports don't pin a
	// misleading positive or negative direction onto an empty line.
	if ( isZero ) {
		return `${ upperCode } ${ withoutCode }`;
	}

	const sign = isNegative ? '-' : '+';

	return `${ sign }${ upperCode } ${ withoutCode }`;
};
