/** @format */

/**
 * SPIKE: render the Balance summary through DataViews using the composition
 * API — pass children so DataViews renders only the pieces we want
 * (the native date Filters + the rows Layout) and omits the Search box,
 * View-options gear, Pagination and Footer. Styling is done with inline
 * style props on the field renders (no custom CSS), per the all-DataViews
 * constraint. See WOOPMNT follow-up.
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

// DataViews exposes its building blocks as statics on the component; the
// public types don't declare them yet, so reach them through a narrow cast.
const DataViewsComposed = DataViews as unknown as React.ComponentType<
	Record< string, unknown > & { children?: React.ReactNode }
>;
const dataViewsStatics = DataViews as unknown as Record<
	string,
	React.ComponentType
>;
const DataViewsFilters = dataViewsStatics.Filters;
const DataViewsLayout = dataViewsStatics.Layout;

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
							style={ {
								display: 'inline-flex',
								alignItems: 'center',
								gap: '8px',
								paddingLeft: `${ item.depth * 16 }px`,
							} }
						>
							{ item.label }
							{ typeof item.count === 'number' && (
								<span
									style={ {
										display: 'inline-flex',
										alignItems: 'center',
										justifyContent: 'center',
										minWidth: '20px',
										height: '20px',
										padding: '0 6px',
										borderRadius: '10px',
										background: '#f0f0f0',
										color: '#1e1e1e',
										fontSize: '11px',
										lineHeight: '20px',
									} }
								>
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
						<span
							style={ { display: 'block', textAlign: 'right' } }
						>
							{ formatBalanceAmount( item.amount, currency ) }
						</span>
					),
				},
			] as Field< BalanceItem >[],
		[ currency, displayPeriod.start ]
	);

	const view = useMemo< View >(
		() => ( {
			type: 'list',
			search: '',
			page: 1,
			perPage: 100,
			titleField: 'label',
			descriptionField: 'amount',
			fields: [ 'amount' ],
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
			<DataViewsComposed
				data={ items }
				view={ view }
				onChangeView={ onChangeView }
				fields={ fields }
				paginationInfo={ { totalItems: items.length, totalPages: 1 } }
				defaultLayouts={ { list: {} } }
				getItemId={ ( item: BalanceItem ) => item.id }
			>
				{ /* Compose only the native date filter + the rows — no
				   Search, View-options gear, Pagination or Footer. */ }
				<DataViewsFilters />
				<DataViewsLayout />
			</DataViewsComposed>
		</div>
	);
};

export default BalanceDataView;
