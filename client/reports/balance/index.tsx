/** @format */

/**
 * External dependencies
 */
import React, { useEffect, useId, useRef, useState } from 'react';
import {
	downloadCSVFile,
	generateCSVDataFromTable,
} from '@woocommerce/csv-export';
import { Button, Icon } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { __, _n, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { calendar } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { useReportsBalanceSummary } from 'wcpay/data';
import DateFilter from 'wcpay/reports/date-filter';
import DownloadButton from 'components/download-button';
import { LoadingReportState } from '../loading-report-state';
import { formatExplicitCurrency } from 'multi-currency/interface/functions';
import { formatDateTimeFromString } from 'wcpay/utils/date-time';
import { BalancePeriod, BalanceRow, getVisibleBalanceRows } from './rows';
import { useBalanceDateFilter } from './use-balance-date-filter';
import WooPaymentsLogo from 'assets/images/woopayments.svg?asset';
import './style.scss';

interface BalanceReportProps {
	onReload?: ( period: BalancePeriod ) => void;
}

const printContextClass = 'wcpay-reports-balance-print-context';

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

const getAmountClassName = ( amount: number ): string =>
	[
		'wcpay-reports-balance__amount',
		amount < 0 && 'wcpay-reports-balance__amount--negative',
		amount > 0 && 'wcpay-reports-balance__amount--positive',
	]
		.filter( Boolean )
		.join( ' ' );

const formatUtcDate = ( value: string ): string =>
	sprintf(
		/* translators: %s: date rendered in the site's date format. */
		__( '%s UTC', 'woocommerce-payments' ),
		formatDateTimeFromString( value, { timezone: 'UTC' } )
	);

const formatYmdUTC = ( value: string ): string => value.slice( 0, 10 );

const getBalanceExportFileName = ( period: BalancePeriod ): string =>
	`wcpay-balance-${ formatYmdUTC( period.start ) }_${ formatYmdUTC(
		period.end
	) }.csv`;

const getPrintRowClassName = ( row: BalanceRow ): string | undefined => {
	const classNames = [
		row.indent && 'wcpay-reports-balance-print__row--indent',
		[ 'fees', 'refunds', 'disputes', 'payouts' ].includes( row.key ) &&
			'wcpay-reports-balance-print__row--group',
	];

	const rowClassName = classNames.filter( Boolean ).join( ' ' );

	return rowClassName || undefined;
};

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

const BalanceEmptyState = (): JSX.Element => (
	<div
		className="wcpay-reports-state wcpay-reports-state--empty wcpay-reports-state--illustrated"
		role="status"
	>
		<span className="wcpay-reports-state__icon" aria-hidden="true">
			<Icon icon={ calendar } size={ 48 } />
		</span>
		<div className="wcpay-reports-state__copy">
			<h2>{ __( 'No balance activity', 'woocommerce-payments' ) }</h2>
			<p>
				{ __(
					"Your Balance summary will appear here once there's enough data to display.",
					'woocommerce-payments'
				) }
			</p>
		</div>
	</div>
);

const BalancePrintReport = ( {
	visibleRows,
	summary,
	displayPeriod,
	currency,
}: {
	visibleRows: BalanceRow[];
	summary: Parameters< BalanceRow[ 'getAmount' ] >[ 0 ];
	displayPeriod: BalancePeriod;
	currency: string;
} ): JSX.Element => (
	<section className="wcpay-reports-balance-print" aria-hidden="true">
		<header className="wcpay-reports-balance-print__header">
			<img
				className="wcpay-reports-balance-print__logo"
				src={ WooPaymentsLogo }
				alt={ __( 'WooPayments', 'woocommerce-payments' ) }
			/>
			<div className="wcpay-reports-balance-print__business">
				<p>{ __( 'WooPayments', 'woocommerce-payments' ) }</p>
				<p>Automattic Inc.</p>
				<p>60 29th Street #343</p>
				<p>San Francisco, CA, 94110, US</p>
			</div>
		</header>
		<table className="wcpay-reports-balance-print__table">
			<thead>
				<tr>
					<th scope="colgroup" colSpan={ 2 }>
						{ __( 'Balance summary', 'woocommerce-payments' ) }
					</th>
				</tr>
			</thead>
			<tbody>
				{ visibleRows.map( ( row ) => {
					const amount = row.getAmount( summary );

					return (
						<tr
							key={ row.key }
							className={ getPrintRowClassName( row ) }
						>
							<th scope="row">
								{ getRowLabel( row, displayPeriod ) }
							</th>
							<td className={ getAmountClassName( amount ) }>
								{ formatExplicitCurrency( amount, currency ) }
							</td>
						</tr>
					);
				} ) }
			</tbody>
		</table>
		<p className="wcpay-reports-balance-print__disclaimer">
			{ __(
				'This report is provided for informational reconciliation purposes only. It is not an IRS form, tax statement, bank statement, legal document, or formal financial statement.',
				'woocommerce-payments'
			) }
		</p>
	</section>
);

export const BalanceReport = ( {
	onReload = () => undefined,
}: BalanceReportProps ): JSX.Element => {
	const { value, period, isDateFilterActive, setValue } =
		useBalanceDateFilter();
	const { createNotice } = useDispatch( 'core/notices' );
	const [ isExporting, setIsExporting ] = useState( false );
	const {
		summary,
		error = {},
		isLoading,
	} = useReportsBalanceSummary( isDateFilterActive ? period : undefined );
	const hasError = hasKeys( error );
	const loadingHeadingRef = useRef< HTMLHeadingElement >( null );
	const previousLoadingRef = useRef( isLoading );
	const errorHeadingId = useId();
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
			void exportError;
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

	const toolbar = (
		<div className="wcpay-reports-balance__toolbar">
			<DateFilter value={ value } onChange={ setValue } />
			<div className="wcpay-reports-balance__toolbar-actions">
				<DownloadButton
					isDisabled={ actionsDisabled || isExporting }
					isBusy={ isExporting }
					onClick={ onExport }
				/>
				<Button
					variant="secondary"
					disabled={ actionsDisabled }
					onClick={ onPrint }
					__next40pxDefaultSize
				>
					{ __( 'Print', 'woocommerce-payments' ) }
				</Button>
			</div>
		</div>
	);

	useEffect( () => {
		if ( previousLoadingRef.current && ! isLoading && ! hasError ) {
			speak(
				__( 'Balance report loaded.', 'woocommerce-payments' ),
				'polite'
			);
		}
		previousLoadingRef.current = isLoading;
	}, [ hasError, isLoading ] );

	useEffect( () => {
		document.body.classList.add( printContextClass );
		document.documentElement.classList.add( printContextClass );

		return () => {
			document.body.classList.remove( printContextClass );
			document.documentElement.classList.remove( printContextClass );
		};
	}, [] );

	let content: JSX.Element;

	if ( ! isDateFilterActive ) {
		content = <BalanceEmptyState />;
	} else if ( isLoading ) {
		content = (
			<>
				<LoadingReportState
					headingRef={ loadingHeadingRef }
					headingTabIndex={ -1 }
				/>
			</>
		);
	} else if ( hasError ) {
		content = (
			<div
				className="wcpay-reports-state wcpay-reports-state--error"
				role="alert"
				aria-labelledby={ errorHeadingId }
			>
				<h2 id={ errorHeadingId } tabIndex={ -1 }>
					{ __( 'Balance unavailable', 'woocommerce-payments' ) }
				</h2>
				<Button
					variant="secondary"
					onClick={ () => onReload( period ) }
				>
					{ __( 'Reload report', 'woocommerce-payments' ) }
				</Button>
			</div>
		);
	} else if ( ! hasActivity ) {
		content = <BalanceEmptyState />;
	} else {
		content = (
			<>
				<h2 className="wcpay-reports-balance__heading">
					{ __( 'Balance summary', 'woocommerce-payments' ) }
				</h2>
				<table
					className="wcpay-reports-balance__table"
					aria-label={ __(
						'Balance summary',
						'woocommerce-payments'
					) }
				>
					<thead className="screen-reader-text">
						<tr>
							<th scope="col">
								{ __( 'Balance row', 'woocommerce-payments' ) }
							</th>
							<th scope="col">
								{ __( 'Explore', 'woocommerce-payments' ) }
							</th>
							<th scope="col">
								{ __( 'Amount', 'woocommerce-payments' ) }
							</th>
						</tr>
					</thead>
					<tbody>
						{ visibleRows.map( ( row ) => {
							const amount = row.getAmount( summary );
							const count = row.getCount?.( summary );
							const countLabel =
								typeof count === 'number'
									? sprintf(
											/* translators: %d: number of ledger entries included in this Balance row. */
											_n(
												'%d item',
												'%d items',
												count,
												'woocommerce-payments'
											),
											count
									  )
									: undefined;
							const rowLabel = getRowLabel( row, displayPeriod );
							const exploreLink = row.exploreLink?.(
								summary,
								displayPeriod
							);

							return (
								<tr
									key={ row.key }
									className={ [
										'wcpay-reports-balance__row',
										row.indent &&
											'wcpay-reports-balance__row--indent',
									]
										.filter( Boolean )
										.join( ' ' ) }
								>
									<th
										scope="row"
										className="wcpay-reports-balance__label"
									>
										<span>{ rowLabel }</span>
										{ typeof count === 'number' && (
											<>
												<span
													className="wcpay-reports-balance__count"
													aria-hidden="true"
												>
													{ count }
												</span>
												<span className="screen-reader-text">
													{ countLabel }
												</span>
											</>
										) }
									</th>
									<td className="wcpay-reports-balance__explore">
										{ exploreLink && (
											<a
												href={ exploreLink }
												aria-label={ sprintf(
													/* translators: %s: Balance report row label. */
													__(
														'Explore %s',
														'woocommerce-payments'
													),
													rowLabel
												) }
											>
												{ __(
													'Explore',
													'woocommerce-payments'
												) }{ ' ' }
												<span aria-hidden="true">
													-&gt;
												</span>
											</a>
										) }
									</td>
									<td
										className={ getAmountClassName(
											amount
										) }
									>
										{ formatExplicitCurrency(
											amount,
											currency
										) }
									</td>
								</tr>
							);
						} ) }
					</tbody>
				</table>
				<BalancePrintReport
					visibleRows={ visibleRows }
					summary={ summary }
					displayPeriod={ displayPeriod }
					currency={ currency }
				/>
			</>
		);
	}

	return (
		<div className="wcpay-reports-balance">
			{ toolbar }
			{ content }
		</div>
	);
};

export default BalanceReport;
