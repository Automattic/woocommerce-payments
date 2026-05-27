/** @format */

/**
 * External dependencies
 */
import React, { useEffect, useId, useRef } from 'react';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { calendar } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { useReportsBalanceSummary } from 'wcpay/data';
import DateFilter from 'wcpay/reports/date-filter';
import { ReportState } from '../report-state';
import type { ReportsPeriodRange } from 'wcpay/reports/period-selector';
import {
	BalanceRow,
	getDisplayedAmount,
	getRowDepth,
	getVisibleBalanceRows,
} from './rows';
import { useBalanceDateFilter } from './use-balance-date-filter';
import { BalanceSummaryTable } from './summary-table';
import { BalanceLoadingSkeleton } from './loading-skeleton';
import { formatBalanceAmount } from './format';
import {
	getRowLabel,
	hasBalanceActivity,
	hasKeys,
	isBalanceSummaryMalformed,
	printContextClass,
} from './utils';
import WooPaymentsLogo from 'assets/images/woopayments.svg?asset';
import './style.scss';

interface BalanceReportProps {
	onReload?: ( period: ReportsPeriodRange ) => void;
}

const woopaymentsBusinessDetails = [
	__( 'WooPayments', 'woocommerce-payments' ),
	// Postal address lines below are deliberately not wrapped in __():
	// localizing a legal entity address would produce non-deliverable text on
	// the printed reconciliation report.
	'Automattic Inc.',
	'60 29th Street #343',
	'San Francisco, CA, 94110, US',
];

const getPrintRowClassName = ( row: BalanceRow ): string | undefined => {
	const depth = getRowDepth( row );
	const classNames = [
		depth === 1 && 'wcpay-reports-balance-print__row--group',
		depth === 2 && 'wcpay-reports-balance-print__row--indent',
	];

	const rowClassName = classNames.filter( Boolean ).join( ' ' );

	return rowClassName || undefined;
};

const BalanceEmptyState = (): JSX.Element => (
	<ReportState
		title={ __( 'No balance activity', 'woocommerce-payments' ) }
		description={ __(
			"Your Balance summary will appear here once there's enough data to display.",
			'woocommerce-payments'
		) }
		icon={ calendar }
		className="wcpay-reports-state--empty"
		role="status"
	/>
);

const BalancePrintReport = ( {
	visibleRows,
	summary,
	displayPeriod,
	currency,
}: {
	visibleRows: BalanceRow[];
	summary: Parameters< BalanceRow[ 'getAmount' ] >[ 0 ];
	displayPeriod: ReportsPeriodRange;
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
				{ woopaymentsBusinessDetails.map( ( line ) => (
					<p key={ line }>{ line }</p>
				) ) }
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
	const { value, period, hasDateFilterValue, setValue } =
		useBalanceDateFilter();
	const {
		summary,
		error = {},
		isLoading,
	} = useReportsBalanceSummary(
		hasDateFilterValue ? period : undefined,
		wcpaySettings.accountDefaultCurrency || ''
	);
	const hasStoreError = hasKeys( error );
	const hasMalformedSummary = isBalanceSummaryMalformed( {
		summary,
		hasDateFilterValue,
		isLoading,
		hasStoreError,
	} );
	const hasError = hasStoreError || hasMalformedSummary;
	const containerRef = useRef< HTMLDivElement >( null );
	const loadingHeadingRef = useRef< HTMLHeadingElement >( null );
	const errorHeadingRef = useRef< HTMLHeadingElement >( null );
	const toolbarRef = useRef< HTMLDivElement >( null );
	const previousLoadingRef = useRef( isLoading );
	const previousErrorRef = useRef( hasError );
	// Marks that the user just pressed Reload, so the next error→loading
	// transition should restore focus to the loading heading (the Reload
	// button itself unmounts before the useEffect runs).
	const reloadRequestedRef = useRef( false );
	const speakTimerRef = useRef< ReturnType< typeof setTimeout > | null >(
		null
	);
	const lastSpokenRef = useRef< string | null >( null );
	const errorHeadingId = useId();
	const errorDescriptionId = useId();
	const visibleRows = getVisibleBalanceRows( summary );
	const hasActivity = hasBalanceActivity( visibleRows, summary );
	const printScopeActive =
		hasDateFilterValue && ! isLoading && ! hasError && hasActivity;
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
			{ hasDateFilterValue && (
				<Button variant="tertiary" onClick={ resetDateFilter }>
					{ __( 'Reset', 'woocommerce-payments' ) }
				</Button>
			) }
		</div>
	);

	useEffect( () => {
		if (
			hasError &&
			! previousErrorRef.current &&
			( containerRef.current?.contains(
				containerRef.current.ownerDocument.activeElement
			) ??
				false )
		) {
			errorHeadingRef.current?.focus();
		}

		// The Reload button is unmounted by the time this effect runs, so we
		// can't read its focus state — `reloadRequestedRef` was set during the
		// click handler instead. The cached error persists alongside
		// `isLoading=true` after invalidateResolution, but the loading
		// skeleton still renders because `isLoading` wins in the content
		// branch — so gate on `isLoading` alone here. The ref is consumed
		// only at the terminal state (success below or the error branch
		// above) so we can also restore focus to the toolbar on
		// Reload → success.
		if ( reloadRequestedRef.current && isLoading ) {
			loadingHeadingRef.current?.focus();
		}

		// Each loading cycle is its own announcement context. Resetting the
		// de-dupe ref on the loading edge keeps duplicate-suppression within
		// a single cycle while letting subsequent successful loads (date
		// filter change, Reload from error) announce again.
		if ( isLoading && ! previousLoadingRef.current ) {
			lastSpokenRef.current = null;
		}

		if ( previousLoadingRef.current && ! isLoading && ! hasError ) {
			if ( reloadRequestedRef.current ) {
				toolbarRef.current
					?.querySelector< HTMLButtonElement >(
						'.wcpay-date-filter__chip-trigger'
					)
					?.focus();
				reloadRequestedRef.current = false;
			}

			const message = __(
				'Balance report loaded.',
				'woocommerce-payments'
			);
			if ( speakTimerRef.current ) {
				clearTimeout( speakTimerRef.current );
			}
			speakTimerRef.current = setTimeout( () => {
				speakTimerRef.current = null;
				if ( lastSpokenRef.current === message ) {
					return;
				}
				lastSpokenRef.current = message;
				speak( message );
			}, 500 );
		}

		// Consume the ref on the error terminal too, so a subsequent
		// non-Reload error doesn't inherit the previous click's intent.
		if ( hasError && ! previousErrorRef.current ) {
			reloadRequestedRef.current = false;
		}

		previousLoadingRef.current = isLoading;
		previousErrorRef.current = hasError;
	}, [ hasError, isLoading ] );

	useEffect( () => {
		if ( ! printScopeActive ) {
			return;
		}

		document.body.classList.add( printContextClass );
		document.documentElement.classList.add( printContextClass );

		return () => {
			document.body.classList.remove( printContextClass );
			document.documentElement.classList.remove( printContextClass );
		};
	}, [ printScopeActive ] );

	useEffect(
		() => () => {
			if ( speakTimerRef.current ) {
				clearTimeout( speakTimerRef.current );
				speakTimerRef.current = null;
			}
		},
		[]
	);

	let content: JSX.Element;

	if ( ! hasDateFilterValue ) {
		content = <BalanceEmptyState />;
	} else if ( isLoading ) {
		content = (
			<BalanceLoadingSkeleton
				headingRef={ loadingHeadingRef }
				headingTabIndex={ -1 }
			/>
		);
	} else if ( hasError ) {
		content = (
			<ReportState
				title={ __( 'Balance unavailable', 'woocommerce-payments' ) }
				description={
					<>
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
					</>
				}
				action={
					<Button
						variant="secondary"
						onClick={ () => {
							reloadRequestedRef.current = true;
							onReload( period );
						} }
					>
						{ __( 'Reload report', 'woocommerce-payments' ) }
					</Button>
				}
				icon={ calendar }
				className="wcpay-reports-state--error wcpay-reports-state--balance-error"
				descriptionId={ errorDescriptionId }
				headingId={ errorHeadingId }
				headingRef={ errorHeadingRef }
				headingTabIndex={ -1 }
				role="alert"
			/>
		);
	} else if ( ! hasActivity ) {
		content = <BalanceEmptyState />;
	} else {
		content = (
			<>
				<BalanceSummaryTable
					visibleRows={ visibleRows }
					summary={ summary }
					displayPeriod={ displayPeriod }
					currency={ currency }
				/>
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
		<div className="wcpay-reports-balance" ref={ containerRef }>
			{ toolbar }
			{ content }
		</div>
	);
};

export default BalanceReport;
