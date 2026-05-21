/** @format */

/**
 * External dependencies
 */
import React, { useEffect, useId, useRef } from 'react';
import { Button } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useReportsBalanceSummary } from 'wcpay/data';
import DateFilter from 'wcpay/reports/date-filter';
import { LoadingReportState } from '../lazy-fees-report';
import { formatExplicitCurrency } from 'multi-currency/interface/functions';
import { formatDateTimeFromString } from 'wcpay/utils/date-time';
import { BalancePeriod, BalanceRow, getVisibleBalanceRows } from './rows';
import { useBalanceDateFilter } from './use-balance-date-filter';
import './style.scss';

interface BalanceReportProps {
	onReload?: () => void;
}

const hasKeys = ( value: Record< string, unknown > | undefined ): boolean =>
	Object.keys( value ?? {} ).length > 0;

const hasBalanceActivity = (
	summary: Parameters< BalanceRow[ 'getAmount' ] >[ 0 ]
): boolean =>
	getVisibleBalanceRows( summary ).some(
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
		className="wcpay-reports-state wcpay-reports-state--empty"
		role="status"
	>
		<h2>{ __( 'No balance activity', 'woocommerce-payments' ) }</h2>
		<p>
			{ __(
				"Your Balance summary will appear here once there's enough data to display.",
				'woocommerce-payments'
			) }
		</p>
	</div>
);

export const BalanceReport = ( {
	onReload = () => undefined,
}: BalanceReportProps ): JSX.Element => {
	const { value, period, setValue } = useBalanceDateFilter();
	const {
		summary,
		error = {},
		isLoading,
	} = useReportsBalanceSummary( period );
	const hasError = hasKeys( error );
	const loadingHeadingRef = useRef< HTMLHeadingElement >( null );
	const errorHeadingRef = useRef< HTMLHeadingElement >( null );
	const previousErrorRef = useRef( hasError );
	const errorHeadingId = useId();

	useEffect( () => {
		if ( hasError && ! previousErrorRef.current ) {
			errorHeadingRef.current?.focus();
		}
		previousErrorRef.current = hasError;
	}, [ hasError ] );

	if ( isLoading ) {
		return (
			<LoadingReportState
				headingRef={ loadingHeadingRef }
				headingTabIndex={ -1 }
			/>
		);
	}

	if ( hasError ) {
		return (
			<div
				className="wcpay-reports-state wcpay-reports-state--error"
				role="group"
				aria-labelledby={ errorHeadingId }
			>
				<h2
					id={ errorHeadingId }
					ref={ errorHeadingRef }
					tabIndex={ -1 }
				>
					{ __( 'Balance unavailable', 'woocommerce-payments' ) }
				</h2>
				<Button variant="secondary" onClick={ onReload }>
					{ __( 'Reload report', 'woocommerce-payments' ) }
				</Button>
			</div>
		);
	}

	if ( ! hasBalanceActivity( summary ) ) {
		return <BalanceEmptyState />;
	}

	const displayPeriod = {
		start: summary.period?.start ?? period.start,
		end: summary.period?.end ?? period.end,
	};
	const visibleRows = getVisibleBalanceRows( summary );
	const currency = summary.currency ?? '';

	return (
		<div className="wcpay-reports-balance">
			<div className="wcpay-reports-balance__toolbar">
				<DateFilter value={ value } onChange={ setValue } />
			</div>
			<h2 className="wcpay-reports-balance__heading">
				{ __( 'Balance summary', 'woocommerce-payments' ) }
			</h2>
			<table
				className="wcpay-reports-balance__table"
				aria-label={ __( 'Balance summary', 'woocommerce-payments' ) }
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
									<span>
										{ getRowLabel( row, displayPeriod ) }
									</span>
									{ typeof count === 'number' && (
										<span
											className="wcpay-reports-balance__count"
											aria-label={ sprintf(
												/* translators: %d: number of ledger entries included in this Balance row. */
												_n(
													'%d item',
													'%d items',
													count,
													'woocommerce-payments'
												),
												count
											) }
										>
											{ count }
										</span>
									) }
								</th>
								<td className="wcpay-reports-balance__explore">
									{ exploreLink && (
										<a href={ exploreLink }>
											{ __(
												'Explore ->',
												'woocommerce-payments'
											) }
										</a>
									) }
								</td>
								<td className={ getAmountClassName( amount ) }>
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
		</div>
	);
};

export default BalanceReport;
