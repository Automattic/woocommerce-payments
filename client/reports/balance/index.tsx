/** @format */

/**
 * External dependencies
 */
import React, { useEffect, useId, useRef } from 'react';
import { Button, Icon } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { calendar } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { useReportsBalanceSummary } from 'wcpay/data';
import DateFilter from 'wcpay/reports/date-filter';
import { LoadingReportState } from '../loading-report-state';
import { formatDateTimeFromString } from 'wcpay/utils/date-time';
import {
	BalancePeriod,
	BalanceRow,
	BalanceRowDepth,
	getVisibleBalanceRows,
} from './rows';
import { useBalanceDateFilter } from './use-balance-date-filter';
import { formatBalanceAmount } from './format';
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

const getRowDepth = ( row: BalanceRow ): BalanceRowDepth => row.depth ?? 0;

const getDisplayedAmount = ( row: BalanceRow, amount: number ): number => {
	// Payouts and friends are always shown as a deduction from the running
	// balance even when the data stores them as a positive magnitude.
	if ( row.displayNegative && amount !== 0 ) {
		return -Math.abs( amount );
	}

	return amount;
};

const formatUtcDate = ( value: string ): string =>
	sprintf(
		/* translators: %s: date rendered in the site's date format. */
		__( '%s UTC', 'woocommerce-payments' ),
		formatDateTimeFromString( value, { timezone: 'UTC' } )
	);

const getPrintRowClassName = ( row: BalanceRow ): string | undefined => {
	const depth = getRowDepth( row );
	const classNames = [
		depth === 1 && 'wcpay-reports-balance-print__row--group',
		depth === 2 && 'wcpay-reports-balance-print__row--indent',
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
				<p>{ __( 'Automattic Inc.', 'woocommerce-payments' ) }</p>
				<p>{ __( '60 29th Street #343', 'woocommerce-payments' ) }</p>
				<p>
					{ __(
						'San Francisco, CA, 94110, US',
						'woocommerce-payments'
					) }
				</p>
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
					const amount = getDisplayedAmount(
						row,
						row.getAmount( summary )
					);

					return (
						<tr
							key={ row.key }
							className={ getPrintRowClassName( row ) }
						>
							<th scope="row">
								{ getRowLabel( row, displayPeriod ) }
							</th>
							<td>{ formatBalanceAmount( amount, currency ) }</td>
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
	const {
		summary,
		error = {},
		isLoading,
	} = useReportsBalanceSummary( isDateFilterActive ? period : undefined );
	const hasError = hasKeys( error );
	const loadingHeadingRef = useRef< HTMLHeadingElement >( null );
	const toolbarRef = useRef< HTMLDivElement >( null );
	const previousLoadingRef = useRef( isLoading );
	const errorHeadingId = useId();
	const errorDescriptionId = useId();
	const visibleRows = getVisibleBalanceRows( summary );
	const hasActivity = hasBalanceActivity( visibleRows, summary );
	const displayPeriod = {
		start: summary.period?.start ?? period.start,
		end: summary.period?.end ?? period.end,
	};
	const currency = summary.currency ?? '';
	const resetDateFilter = () => {
		toolbarRef.current
			?.querySelector< HTMLButtonElement >(
				'.wcpay-date-filter__chip-trigger'
			)
			?.focus();
		setValue( undefined );
	};

	const toolbar = (
		<div className="wcpay-reports-balance__toolbar" ref={ toolbarRef }>
			<DateFilter value={ value } onChange={ setValue } />
			{ isDateFilterActive && (
				<Button variant="tertiary" onClick={ resetDateFilter }>
					{ __( 'Reset', 'woocommerce-payments' ) }
				</Button>
			) }
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
				className="wcpay-reports-state wcpay-reports-state--error wcpay-reports-state--illustrated wcpay-reports-state--balance-error"
				role="alert"
				aria-labelledby={ errorHeadingId }
				aria-describedby={ errorDescriptionId }
			>
				<span className="wcpay-reports-state__icon" aria-hidden="true">
					<Icon icon={ calendar } size={ 48 } />
				</span>
				<div className="wcpay-reports-state__copy">
					<h2 id={ errorHeadingId }>
						{ __( 'Balance unavailable', 'woocommerce-payments' ) }
					</h2>
					<p id={ errorDescriptionId }>
						<span>
							{ __(
								"We couldn't load your balance data.",
								'woocommerce-payments'
							) }
						</span>{ ' ' }
						<span>
							{ __(
								'Try again in a few minutes.',
								'woocommerce-payments'
							) }
						</span>
					</p>
				</div>
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
				<div className="wcpay-reports-balance__card">
					<table
						className="wcpay-reports-balance__table"
						aria-label={ __(
							'Balance summary',
							'woocommerce-payments'
						) }
					>
						<caption className="wcpay-reports-balance__caption">
							{ __( 'Balance summary', 'woocommerce-payments' ) }
						</caption>
						<thead className="screen-reader-text">
							<tr>
								<th scope="col">
									{ __(
										'Balance row',
										'woocommerce-payments'
									) }
								</th>
								<th scope="col">
									{ __( 'Amount', 'woocommerce-payments' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ visibleRows.map( ( row ) => {
								const amount = getDisplayedAmount(
									row,
									row.getAmount( summary )
								);
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
								const rowLabel = getRowLabel(
									row,
									displayPeriod
								);
								const exploreLink = row.exploreLink?.(
									summary,
									displayPeriod
								);
								const depth = getRowDepth( row );

								return (
									<tr
										key={ row.key }
										className={ [
											'wcpay-reports-balance__row',
											`wcpay-reports-balance__row--depth-${ depth }`,
										].join( ' ' ) }
									>
										<th
											scope="row"
											className="wcpay-reports-balance__label"
										>
											<div className="wcpay-reports-balance__label-inner">
												<span className="wcpay-reports-balance__label-text">
													{ rowLabel }
												</span>
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
												{ exploreLink && (
													<a
														className="wcpay-reports-balance__explore"
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
															&rarr;
														</span>
													</a>
												) }
											</div>
										</th>
										<td className="wcpay-reports-balance__amount">
											{ formatBalanceAmount(
												amount,
												currency
											) }
										</td>
									</tr>
								);
							} ) }
						</tbody>
					</table>
				</div>
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
