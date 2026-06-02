/** @format */

/**
 * SPIKE: render the Balance summary through DataViews so the native DataViews
 * date filter can replace the custom <DateFilter>. The summary is not a list,
 * so this is an exploratory fit — see WOOPMNT follow-up.
 *
 * External dependencies
 */
import React, { useMemo } from 'react';
import { __ } from '@wordpress/i18n';
import { DataViews } from '@wordpress/dataviews/wp';
import type { View, Field } from '@wordpress/dataviews/wp';

/**
 * Internal dependencies
 */
import { BalanceRow, getDisplayedAmount, getRowDepth } from './rows';
import { formatBalanceAmount } from './format';
import { getRowLabel } from './utils';
import type { ReportsBalanceSummary } from 'wcpay/data/reports/hooks';
import type { ReportsPeriodRange } from 'wcpay/reports/period-selector';
import type { DateFilterValue } from 'wcpay/reports/date-filter';

interface BalanceItem {
	id: string;
	label: string;
	depth: number;
	count?: number;
	amount: number;
}

interface BalanceDataViewProps {
	visibleRows: BalanceRow[];
	summary: ReportsBalanceSummary;
	displayPeriod: ReportsPeriodRange;
	currency: string;
	dateValue: DateFilterValue | undefined;
	onDateChange: ( next: DateFilterValue | undefined ) => void;
}

const buildItems = (
	visibleRows: BalanceRow[],
	summary: ReportsBalanceSummary,
	displayPeriod: ReportsPeriodRange
): BalanceItem[] =>
	visibleRows.map( ( row ) => ( {
		id: row.key,
		label: getRowLabel( row, displayPeriod ),
		depth: getRowDepth( row ),
		count: row.getCount?.( summary ),
		amount: getDisplayedAmount( row, row.getAmount( summary ) ),
	} ) );

export const BalanceDataView = ( {
	visibleRows,
	summary,
	displayPeriod,
	currency,
	dateValue,
	onDateChange,
}: BalanceDataViewProps ): JSX.Element => {
	const items = useMemo(
		() => buildItems( visibleRows, summary, displayPeriod ),
		[ visibleRows, summary, displayPeriod ]
	);

	const fields = useMemo< Field< BalanceItem >[] >(
		() =>
			[
				{
					id: 'date',
					label: __( 'Date', 'woocommerce-payments' ),
					type: 'date',
					// Filter-only: there is no per-row date on a summary line, so
					// the field exists purely to surface the native date filter.
					// Period selection is handled server-side via onDateChange.
					enableHiding: false,
					enableSorting: false,
					filterBy: {
						isPrimary: true,
						operators: [ 'before', 'after', 'between', 'on' ],
					},
					getValue: () => displayPeriod.start,
				},
				{
					id: 'label',
					label: __( 'Balance row', 'woocommerce-payments' ),
					enableSorting: false,
					getValue: ( { item }: { item: BalanceItem } ) => item.label,
					render: ( { item }: { item: BalanceItem } ) => (
						<span
							className={ `wcpay-reports-balance-dv__label wcpay-reports-balance-dv__label--depth-${ item.depth }` }
						>
							{ item.label }
							{ typeof item.count === 'number' && (
								<span className="wcpay-reports-balance-dv__count">
									{ item.count }
								</span>
							) }
						</span>
					),
				},
				{
					id: 'amount',
					label: __( 'Amount', 'woocommerce-payments' ),
					enableSorting: false,
					getValue: ( { item }: { item: BalanceItem } ) =>
						item.amount,
					render: ( { item }: { item: BalanceItem } ) => (
						<>{ formatBalanceAmount( item.amount, currency ) }</>
					),
				},
			] as Field< BalanceItem >[],
		[ currency, displayPeriod.start ]
	);

	const view = useMemo< View >(
		() => ( {
			type: 'table',
			search: '',
			page: 1,
			perPage: 100,
			fields: [ 'label', 'amount' ],
			filters: dateValue
				? [
						{
							field: 'date',
							operator: dateValue.operator,
							value: dateValue.value,
						},
				  ]
				: [],
			layout: {},
		} ),
		[ dateValue ]
	);

	const onChangeView = ( next: View ) => {
		const dateFilter = next.filters?.find( ( f ) => f.field === 'date' );
		if ( dateFilter && dateFilter.value !== undefined ) {
			onDateChange( {
				operator: dateFilter.operator,
				value: dateFilter.value,
			} as DateFilterValue );
		} else if ( dateValue ) {
			onDateChange( undefined );
		}
	};

	return (
		<div className="wcpay-reports-balance-dv">
			<DataViews
				data={ items }
				view={ view }
				onChangeView={ onChangeView }
				fields={ fields }
				paginationInfo={ {
					totalItems: items.length,
					totalPages: 1,
				} }
				defaultLayouts={ { table: {} } }
				getItemId={ ( item ) => item.id }
			/>
		</div>
	);
};

export default BalanceDataView;
