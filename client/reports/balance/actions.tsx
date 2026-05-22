/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import {
	downloadCSVFile,
	generateCSVDataFromTable,
} from '@woocommerce/csv-export';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useReportsBalanceSummary } from 'wcpay/data';
import type { ReportsPeriodRange } from 'wcpay/reports/period-selector';
import { getVisibleBalanceRows, type BalanceRow } from './rows';
import { useBalanceDateFilter } from './use-balance-date-filter';
import {
	getRowLabel,
	hasBalanceActivity,
	hasKeys,
	isBalanceSummaryMalformed,
} from './utils';

const formatYmdUTC = ( value: string ): string => value.slice( 0, 10 );

const getBalanceExportFileName = ( period: ReportsPeriodRange ): string =>
	`wcpay-balance-${ formatYmdUTC( period.start ) }_${ formatYmdUTC(
		period.end
	) }.csv`;

const getBalanceCSV = ( {
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

	return generateCSVDataFromTable(
		[
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
				{ value: row.key, display: row.key },
				{
					value: rowLabel,
					display: rowLabel,
				},
				{ value: row.getAmount( summary ), display: '' },
				{ value: count ?? '', display: '' },
				{ value: currency, display: currency },
				{
					value: periodStart,
					display: periodStart,
				},
				{
					value: periodEnd,
					display: periodEnd,
				},
			];
		} )
	);
};

export const BalanceActions = (): JSX.Element => {
	const { period, hasDateFilterValue } = useBalanceDateFilter();
	const { createNotice } = useDispatch( 'core/notices' );
	const {
		summary,
		error = {},
		isLoading,
	} = useReportsBalanceSummary(
		hasDateFilterValue ? period : undefined,
		wcpaySettings.accountDefaultCurrency || ''
	);
	const hasStoreError = hasKeys( error );
	const visibleRows = getVisibleBalanceRows( summary );
	const hasActivity = hasBalanceActivity( visibleRows, summary );
	const hasMalformedSummary = isBalanceSummaryMalformed( {
		summary,
		hasDateFilterValue,
		isLoading,
		hasStoreError,
	} );
	const displayPeriod = {
		start: summary.period?.start ?? period.start,
		end: summary.period?.end ?? period.end,
	};
	const currency = summary.currency ?? '';
	const actionsDisabled =
		! hasDateFilterValue ||
		isLoading ||
		hasStoreError ||
		hasMalformedSummary ||
		! hasActivity;

	const onExport = () => {
		if ( actionsDisabled ) {
			return;
		}

		try {
			downloadCSVFile(
				getBalanceExportFileName( displayPeriod ),
				getBalanceCSV( {
					visibleRows,
					summary,
					displayPeriod,
					currency,
				} )
			);
		} catch ( exportError ) {
			// eslint-disable-next-line no-console
			console.error( 'Balance CSV export failed:', exportError );
			createNotice(
				'error',
				__(
					'There was a problem generating your export.',
					'woocommerce-payments'
				)
			);
		}
	};

	const onPrint = () => {
		if ( actionsDisabled ) {
			return;
		}

		window.print();
	};

	return (
		<div className="wcpay-reports-balance-actions">
			<Button
				variant="secondary"
				disabled={ actionsDisabled }
				accessibleWhenDisabled
				onClick={ onPrint }
				__next40pxDefaultSize
			>
				{ __( 'Print', 'woocommerce-payments' ) }
			</Button>
			<Button
				variant="primary"
				disabled={ actionsDisabled }
				accessibleWhenDisabled
				onClick={ onExport }
				__next40pxDefaultSize
			>
				{ __( 'Export', 'woocommerce-payments' ) }
			</Button>
		</div>
	);
};

export default BalanceActions;
