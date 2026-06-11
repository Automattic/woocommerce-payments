/** @format */

/**
 * Renders the Balance summary through DataViews using the composition API —
 * passing children makes DataViews render only the composed pieces (the
 * funnel FiltersToggle, the native date filter chips and the rows Layout)
 * and omit the Search box, View-options gear, Pagination and Footer.
 * Field renders carry their styling inline; a scoped block in style.scss
 * neutralises the DataViews table chrome (header row, cell padding) so the
 * summary matches the bespoke design it replaced.
 *
 * External dependencies
 */
import React, { useEffect, useMemo, useRef, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
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
const DataViewsFiltersToggled = dataViewsStatics.FiltersToggled;
const DataViewsFiltersToggle = dataViewsStatics.FiltersToggle;
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
	// Render as a non-interactive preview (the loading skeleton): hide the
	// native date Filters and mark the whole view aria-hidden so the blurred
	// placeholder is skipped by assistive tech and keyboard navigation.
	preview?: boolean;
	// When provided, render this below the date Filters instead of the rows
	// card. Keeps the primary Date chip mounted across the loading / error /
	// empty report states, so a cleared filter can always be re-applied.
	children?: React.ReactNode;
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
	preview = false,
	children,
}: BalanceDataViewProps ): JSX.Element => {
	// A filter entry the user is still editing (added via the funnel menu, or
	// an operator switch that reset the value). The chip must stay rendered —
	// with its date input — even though there's no applied value yet, and the
	// controlled `view` below is otherwise derived solely from `dateValue`.
	const [ pendingDateOperator, setPendingDateOperator ] = useState<
		string | null
	>( null );
	const rootRef = useRef< HTMLDivElement >( null );

	// DataViews keeps the chips row collapsed until the funnel is toggled —
	// `isShowingFilter` starts false for non-primary filters and isn't part
	// of the public API. Open it once on mount so the active Date chip shows
	// by default; the funnel still collapses it afterwards. The selector only
	// matches the funnel in toggle mode (an active filter exists) — the
	// "Add filter" menu trigger carries aria-haspopup and is skipped.
	useEffect( () => {
		if ( preview ) {
			return;
		}
		rootRef.current
			?.querySelector< HTMLButtonElement >(
				'.dataviews-filters__visibility-toggle[aria-pressed="false"]:not([aria-haspopup])'
			)
			?.click();
	}, [ preview ] );

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
						// Deliberately not primary: a cleared filter removes
						// the chip and leaves only the funnel toggle, which
						// re-adds it (primary filters also hard-disable the
						// funnel in DataViews).
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
								gap: '12px',
								paddingLeft: `${ item.depth * 24 }px`,
								fontWeight: item.depth === 2 ? 400 : 500,
								color: item.depth === 2 ? '#757575' : '#2f2f2f',
							} }
						>
							{ item.label }
							{ typeof item.count === 'number' && (
								<>
									{ /* The badge is decorative; screen readers
									   get the unambiguous "N items" text instead. */ }
									<span
										aria-hidden="true"
										style={ {
											display: 'inline-flex',
											alignItems: 'center',
											justifyContent: 'center',
											minWidth: '20px',
											height: '20px',
											padding: '0 6px',
											borderRadius: '10px',
											background: '#f0f0f0',
											color: '#757575',
											fontSize: '11px',
											fontWeight: 500,
											lineHeight: '20px',
										} }
									>
										{ item.count }
									</span>
									<span className="screen-reader-text">
										{ sprintf(
											/* translators: %d: number of ledger entries included in this Balance row. */
											_n(
												'%d item',
												'%d items',
												item.count,
												'woocommerce-payments'
											),
											item.count
										) }
									</span>
								</>
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
						// DataViews wraps cell content in a flex container, so
						// grow to fill it before right-aligning the text.
						<span
							style={ {
								flexGrow: 1,
								textAlign: 'right',
								fontVariantNumeric: 'tabular-nums',
								fontWeight: item.depth >= 1 ? 400 : 500,
								color: item.depth === 2 ? '#757575' : '#2f2f2f',
							} }
						>
							{ formatBalanceAmount( item.amount, currency ) }
						</span>
					),
				},
			] as Field< BalanceItem >[],
		[ currency, displayPeriod.start ]
	);

	const view = useMemo< View >( () => {
		// A pending (valueless) entry wins so the chip reflects the edit in
		// progress; the previously applied value keeps driving the data below
		// until a new date is actually picked.
		let filters: View[ 'filters' ] = [];
		if ( pendingDateOperator ) {
			filters = [
				{
					field: 'date',
					operator:
						pendingDateOperator as DateFilterValue[ 'operator' ],
					value: undefined,
				},
			];
		} else if ( dateValue ) {
			filters = [
				{
					field: 'date',
					operator: dateValue.operator,
					value: dateValue.value,
				},
			];
		}

		return {
			type: 'table',
			search: '',
			page: 1,
			perPage: 100,
			fields: [ 'label', 'amount' ],
			filters,
			layout: {},
		};
	}, [ dateValue, pendingDateOperator ] );

	const onChangeView = ( next: View ) => {
		const dateFilter = next.filters?.find( ( f ) => f.field === 'date' );
		if ( dateFilter && dateFilter.value !== undefined ) {
			setPendingDateOperator( null );
			onDateChange( {
				operator: dateFilter.operator,
				value: dateFilter.value,
			} as DateFilterValue );
			return;
		}
		if ( dateFilter ) {
			// Filter added from the funnel menu, or an operator switch reset
			// the value — keep the chip mounted while the user picks a date.
			setPendingDateOperator( dateFilter.operator );
			return;
		}
		setPendingDateOperator( null );
		if ( dateValue ) {
			onDateChange( undefined );
		}
	};

	return (
		<div
			className="wcpay-reports-balance-dv"
			ref={ rootRef }
			{ ...( preview ? { 'aria-hidden': true } : {} ) }
		>
			<DataViewsComposed
				data={ items }
				view={ view }
				onChangeView={ onChangeView }
				fields={ fields }
				paginationInfo={ { totalItems: items.length, totalPages: 1 } }
				defaultLayouts={ { table: {} } }
				getItemId={ ( item: BalanceItem ) => item.id }
			>
				{ /* Compose only the native date filter + the rows — no
				   Search, View-options gear, Pagination or Footer. The preview
				   (loading skeleton) omits the interactive filter entirely.
				   The funnel toggle is always mounted, positioned next to the
				   report tabs (see style.scss): with an active filter it shows
				   a count badge and toggles the chips row; with none, its menu
				   re-adds the Date filter. */ }
				{ ! preview && (
					<div className="wcpay-reports-balance-dv__actions">
						<DataViewsFiltersToggle />
					</div>
				) }
				{ ! preview && <DataViewsFiltersToggled /> }
				{ children ?? (
					<div
						style={ {
							background: '#fff',
							border: '1px solid #e0e0e0',
							borderRadius: '8px',
							padding: '16px 24px 24px',
							marginTop: '16px',
						} }
					>
						<div
							style={ {
								padding: '12px 0 16px',
								fontSize: '15px',
								fontWeight: 500,
								lineHeight: '20px',
							} }
						>
							{ __( 'Balance summary', 'woocommerce-payments' ) }
						</div>
						<DataViewsLayout />
					</div>
				) }
			</DataViewsComposed>
		</div>
	);
};

export default BalanceDataView;
