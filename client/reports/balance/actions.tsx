/** @format */

/**
 * External dependencies
 */
import React, { useState } from 'react';
import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import {
	downloadCSVFile,
	generateCSVDataFromTable,
} from '@woocommerce/csv-export';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useReportsBalanceSummary } from 'wcpay/data/reports/hooks';
import { formatDateTimeFromString } from 'wcpay/utils/date-time';
import { BalancePeriod, BalanceRow, getVisibleBalanceRows } from './rows';
import { useBalanceDateFilter } from './use-balance-date-filter';

const formatYmdUTC = ( value: string ): string => value.slice( 0, 10 );

const formatUtcDate = ( value: string ): string =>
	sprintf(
		/* translators: %s: date rendered in the site's date format. */
		__( '%s UTC', 'woocommerce-payments' ),
		formatDateTimeFromString( value, { timezone: 'UTC' } )
	);

const getRowLabel = ( row: BalanceRow, period: BalancePeriod ): string => {
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

const getBalanceExportFileName = ( period: BalancePeriod ): string =>
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
	displayPeriod: BalancePeriod;
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

const hasKeys = ( value: Record< string, unknown > | undefined ): boolean =>
	Object.keys( value ?? {} ).length > 0;

const hasBalanceActivity = (
	visibleRows: BalanceRow[],
	summary: Parameters< BalanceRow[ 'getAmount' ] >[ 0 ]
): boolean =>
	visibleRows.some(
		( row ) =>
			row.getAmount( summary ) !== 0 ||
			( row.getCount?.( summary ) ?? 0 ) !== 0
	);

export const BalanceActions = (): JSX.Element => {
	const { period, isDateFilterActive } = useBalanceDateFilter();
	const { createNotice } = useDispatch( 'core/notices' );
	const [ isExporting, setIsExporting ] = useState( false );
	const {
		summary,
		error = {},
		isLoading,
	} = useReportsBalanceSummary( isDateFilterActive ? period : undefined );
	const hasError = hasKeys( error );
	const visibleRows = getVisibleBalanceRows( summary );
	const hasActivity = hasBalanceActivity( visibleRows, summary );
	const displayPeriod = {
		start: summary.period?.start ?? period.start,
		end: summary.period?.end ?? period.end,
	};
	const currency = summary.currency ?? '';
	const actionsDisabled =
		! isDateFilterActive || isLoading || hasError || ! hasActivity;

	const onExport = () => {
		setIsExporting( true );

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
		} finally {
			setIsExporting( false );
		}
	};

	const onPrint = () => window.print();

	return (
		<div className="wcpay-reports-balance-actions">
			<Button
				variant="secondary"
				disabled={ actionsDisabled }
				onClick={ onPrint }
				__next40pxDefaultSize
			>
				{ __( 'Print', 'woocommerce-payments' ) }
			</Button>
			<Button
				variant="primary"
				disabled={ actionsDisabled || isExporting }
				isBusy={ isExporting }
				onClick={ onExport }
				__next40pxDefaultSize
			>
				{ __( 'Export', 'woocommerce-payments' ) }
			</Button>
		</div>
	);
};

export default BalanceActions;
