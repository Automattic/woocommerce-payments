/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { BalanceRow, getDisplayedAmount, getRowDepth } from './rows';
import { formatBalanceAmount } from './format';
import { getRowLabel } from './utils';
import type { ReportsBalanceSummary } from 'wcpay/data/reports/hooks';
import type { ReportsPeriodRange } from 'wcpay/reports/period-selector';

interface BalanceSummaryTableProps {
	visibleRows: BalanceRow[];
	summary: ReportsBalanceSummary;
	displayPeriod: ReportsPeriodRange;
	currency: string;
	className?: string;
	ariaHidden?: boolean;
}

export const BalanceSummaryTable = ( {
	visibleRows,
	summary,
	displayPeriod,
	currency,
	className,
	ariaHidden = false,
}: BalanceSummaryTableProps ): JSX.Element => {
	const cardClassName = [ 'wcpay-reports-balance__card', className ]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div
			className={ cardClassName }
			{ ...( ariaHidden ? { 'aria-hidden': true } : {} ) }
		>
			<table className="wcpay-reports-balance__table">
				<caption className="wcpay-reports-balance__caption">
					{ __( 'Balance summary', 'woocommerce-payments' ) }
				</caption>
				<thead className="screen-reader-text">
					<tr>
						<th scope="col">
							{ __( 'Balance row', 'woocommerce-payments' ) }
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
						const rowLabel = getRowLabel( row, displayPeriod );
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
									</div>
								</th>
								<td className="wcpay-reports-balance__amount">
									{ formatBalanceAmount( amount, currency ) }
								</td>
							</tr>
						);
					} ) }
				</tbody>
			</table>
		</div>
	);
};
