/** @format */

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { formatDateTimeFromString } from 'wcpay/utils/date-time';
import type { ReportsBalanceSummary } from 'wcpay/data/reports/hooks';
import type { ReportsPeriodRange } from 'wcpay/reports/period-selector';
import type { BalanceRow } from './rows';

export const printContextClass = 'wcpay-reports-balance-print-context';

export const getRangeDays = ( start?: string, end?: string ): number | null => {
	if ( ! start || ! end ) {
		return null;
	}

	return Math.round(
		( new Date( end ).getTime() - new Date( start ).getTime() ) / 86400000
	);
};

export const hasKeys = (
	value: Record< string, unknown > | undefined
): boolean => Object.keys( value ?? {} ).length > 0;

export const isBalanceSummaryValid = (
	summary: ReportsBalanceSummary
): boolean =>
	Boolean( summary.currency && summary.period?.start && summary.period?.end );

export const isBalanceSummaryMalformed = ( {
	summary,
	hasDateFilterValue,
	isLoading,
	hasStoreError,
}: {
	summary: ReportsBalanceSummary;
	hasDateFilterValue: boolean;
	isLoading: boolean;
	hasStoreError: boolean;
} ): boolean =>
	hasDateFilterValue &&
	! isLoading &&
	! hasStoreError &&
	! isBalanceSummaryValid( summary );

export const hasBalanceActivity = (
	visibleRows: BalanceRow[],
	summary: Parameters< BalanceRow[ 'getAmount' ] >[ 0 ]
): boolean =>
	visibleRows.some(
		( row ) =>
			row.getAmount( summary ) !== 0 ||
			( row.getCount?.( summary ) ?? 0 ) !== 0
	);

export const formatUtcDate = ( value: string ): string =>
	sprintf(
		/* translators: %s: date rendered in the site's date format. */
		__( '%s UTC', 'woocommerce-payments' ),
		formatDateTimeFromString( value, { timezone: 'UTC' } )
	);

export const getRowLabel = (
	row: BalanceRow,
	period: ReportsPeriodRange
): string => {
	if ( row.key === 'starting_balance' ) {
		return sprintf(
			/* translators: %s: period start date. */
			__( 'Starting balance - %s', 'woocommerce-payments' ),
			formatUtcDate( period.start )
		);
	}

	if ( row.key === 'ending_balance' ) {
		return sprintf(
			/* translators: %s: period end date. */
			__( 'Ending balance - %s', 'woocommerce-payments' ),
			formatUtcDate( period.end )
		);
	}

	return row.label;
};
