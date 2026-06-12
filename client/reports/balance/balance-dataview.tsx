/** @format */

/**
 * Renders the Balance summary through DataViews using the composition API —
 * passing children makes DataViews render only the composed pieces (the
 * funnel FiltersToggle, the native date filter chips and the rows Layout)
 * and omit the Search box, View-options gear, Pagination and Footer.
 * Field renders carry className-driven styling so depth colors live in SCSS
 * (overridable in forced-colors mode); a scoped block in style.scss also
 * neutralises the DataViews table chrome (header row, cell padding) so the
 * summary matches the bespoke design it replaced.
 *
 * External dependencies
 */
import React, {
	useCallback,
	useEffect,
	useId,
	useMemo,
	useRef,
	useState,
} from 'react';
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

// DataViews keeps the chips row collapsed until the funnel is toggled —
// `isShowingFilter` starts false for non-primary filters and isn't part of the
// public API. We open it once by clicking the funnel button so the active Date
// chip shows by default. The selector is scoped to the funnel-in-toggle-mode
// form (an active filter exists); the "Add filter" menu trigger carries
// aria-haspopup and is intentionally skipped so a filterless mount never pops
// the menu.
//
// This is a deliberate, product-required divergence from the Fees report —
// do not reconcile the two: Balance keeps its date filter open by default
// (it's the report's only control and always has an applied range), while
// Fees ships with no filters applied and accepts DataViews' collapsed
// default (chip hidden behind the funnel badge until clicked). The
// canonical-rows test pins the open-by-default behaviour.
// TODO: Replace with a public DataViews API (e.g. a `defaultFiltersOpen` prop
// or an `isShowingFilter` setter) once one ships upstream.
const openFiltersRowWorkaround = ( root: HTMLElement | null ): void => {
	root?.querySelector< HTMLButtonElement >(
		'.dataviews-filters__visibility-toggle[aria-pressed="false"]:not([aria-haspopup])'
	)?.click();
};

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
	// card. Keeps the date filter UI (funnel toggle + active chip) mounted
	// across the loading / error / empty report states, so a cleared filter
	// can always be re-applied.
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
	// Unlike Fees (which stages this in useFeesView next to its URL sync),
	// Balance has no view-state URL sync, so component state is the right
	// home for it.
	const [ pendingDateOperator, setPendingDateOperator ] = useState<
		string | null
	>( null );
	const rootRef = useRef< HTMLDivElement >( null );
	// DataViews offers no way to name the <table> itself, so the card is a
	// labelled group: assistive tech announces "Balance summary" on entry,
	// standing in for the bespoke table's <caption>.
	const captionId = useId();
	// Gate the funnel-click workaround to a single run per mount so React
	// 18 StrictMode's double-invoke in development doesn't fire it twice.
	const filtersRowOpenedRef = useRef( false );

	useEffect( () => {
		if ( preview || filtersRowOpenedRef.current ) {
			return;
		}
		openFiltersRowWorkaround( rootRef.current );
		filtersRowOpenedRef.current = true;
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
					// With sorting, hiding and moving all disabled, DataViews
					// renders the column header as plain text instead of a
					// menu button — required for the visually-hidden <thead>
					// to stay free of focusable elements.
					enableHiding: false,
					enableSorting: false,
					getValue: ( { item }: { item: BalanceItem } ) => item.label,
					render: ( { item }: { item: BalanceItem } ) => (
						<span
							className={ `wcpay-reports-balance-dv__label wcpay-reports-balance-dv__label--depth-${ item.depth }` }
						>
							{ item.label }
							{ typeof item.count === 'number' && (
								<>
									{ /* The badge is decorative; screen readers
									   get the unambiguous "N items" text instead. */ }
									<span
										aria-hidden="true"
										className="wcpay-reports-balance-dv__count"
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
					enableHiding: false,
					enableSorting: false,
					getValue: ( { item }: { item: BalanceItem } ) =>
						item.amount,
					render: ( { item }: { item: BalanceItem } ) => (
						<span
							className={ `wcpay-reports-balance-dv__amount wcpay-reports-balance-dv__amount--depth-${ item.depth }` }
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
			// Column moving is part of the header menus — disabling it (with
			// sorting/hiding off on the fields) keeps the visually-hidden
			// column headers as plain, non-focusable text.
			layout: { enableMoving: false },
		};
	}, [ dateValue, pendingDateOperator ] );

	// `items`, `fields` and `view` are memoized above; memoizing the handler
	// too keeps the DataViews prop set referentially stable between renders.
	const onChangeView = useCallback(
		( next: View ) => {
			const dateFilter = next.filters?.find(
				( f ) => f.field === 'date'
			);
			if ( dateFilter && dateFilter.value !== undefined ) {
				setPendingDateOperator( null );
				onDateChange( {
					operator: dateFilter.operator,
					value: dateFilter.value,
				} as DateFilterValue );
				return;
			}
			if ( dateFilter ) {
				// Filter added from the funnel menu, or an operator switch
				// reset the value — keep the chip mounted while the user
				// picks a date.
				setPendingDateOperator( dateFilter.operator );
				return;
			}
			setPendingDateOperator( null );
			if ( dateValue ) {
				onDateChange( undefined );
			}
		},
		[ dateValue, onDateChange ]
	);

	return (
		<div
			className="wcpay-reports-balance-dv"
			ref={ rootRef }
			aria-hidden={ preview || undefined }
		>
			<DataViews
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
						<DataViews.FiltersToggle />
					</div>
				) }
				{ ! preview && <DataViews.FiltersToggled /> }
				{ children ?? (
					<div
						className="wcpay-reports-balance-dv__card"
						role="group"
						aria-labelledby={ captionId }
					>
						<div
							className="wcpay-reports-balance-dv__caption"
							id={ captionId }
						>
							{ __( 'Balance summary', 'woocommerce-payments' ) }
						</div>
						<DataViews.Layout />
					</div>
				) }
			</DataViews>
		</div>
	);
};

export default BalanceDataView;
