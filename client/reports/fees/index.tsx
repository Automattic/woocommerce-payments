/** @format */

/**
 * External dependencies
 */
import React, {
	useCallback,
	useEffect,
	useLayoutEffect,
	useMemo,
	useRef,
	useState,
} from 'react';
import { createPortal } from 'react-dom';
import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { DataViews } from '@wordpress/dataviews';

/**
 * Internal dependencies
 */
import DownloadButton from 'wcpay/components/download-button';
import { useReportExport } from 'wcpay/hooks/use-report-export';
import {
	getReportsFeesCSVRequestURL,
	reportsFeesDownloadEndpoint,
} from 'wcpay/data/reports/resolvers';
import { recordEvent } from 'tracks';
import { DateFilter } from 'wcpay/reports/date-filter';
import type { DateFilterValue } from 'wcpay/reports/date-filter';
import { useFeesView } from './use-fees-view';
import { useFeesData } from './use-fees-data';
import { useFeesDateFilter } from './use-fees-date-filter';
import { dateFilterAnchorFieldId, getFeesFields } from './fields';
import type { ReportsPeriodRange } from '../period-selector';
import type { Filter, View, ViewTable } from '@wordpress/dataviews';

// Synthetic filter entry attached to the anchor field whenever `dateFilter`
// is set. It carries no real filtering semantics (`useFeesData` / `useFeesView`
// only read `payment_method` and `type`) — its sole purpose is to flip
// DataViews' built-in Reset button from disabled to enabled, so Reset can
// surface naturally (no custom styling, no aria-disabled overrides) and
// participate in the unified "clear everything" action.
const dateFilterAnchorValue = '__anchor__';
const dateAnchorFilter: Filter = {
	field: dateFilterAnchorFieldId,
	operator: 'is',
	value: dateFilterAnchorValue,
};

interface FeesReportProps {
	period: ReportsPeriodRange;
	onReload?: () => void;
}

/**
 * Returns true when the user's visible-fields configuration has changed.
 * Compared as joined strings — the field list is small and order-significant.
 */
const haveFieldsChanged = (
	prev: ReadonlyArray< string > = [],
	next: ReadonlyArray< string > = []
): boolean => prev.join( '|' ) !== next.join( '|' );

/**
 * Returns true when the standalone date filter (operator + value) differs.
 * Used to scope the `wcpay_reports_date_range_changed` analytics event.
 */
const hasDateFilterChanged = (
	prev: DateFilterValue | undefined,
	next: DateFilterValue | undefined
): boolean => {
	if ( ! prev && ! next ) {
		return false;
	}
	if ( ! prev || ! next ) {
		return true;
	}
	return (
		prev.operator !== next.operator ||
		JSON.stringify( prev.value ) !== JSON.stringify( next.value )
	);
};

export const FeesReport = ( {
	period,
	onReload = () => undefined,
}: FeesReportProps ): JSX.Element => {
	const [ view, setView ] = useFeesView();
	const [ dateFilter, setDateFilter ] = useFeesDateFilter();
	const {
		feesQuery,
		rows,
		totalItems,
		totalPages,
		methodElements,
		typeElements,
		isLoading,
		error,
	} = useFeesData( view, dateFilter, period );
	const { requestReportExport, isExportInProgress } = useReportExport();
	const { createNotice } = useDispatch( 'core/notices' );

	const fields = useMemo(
		() => getFeesFields( { methodElements, typeElements } ),
		[ methodElements, typeElements ]
	);

	// Pass DataViews an "augmented" view that injects the synthetic anchor
	// filter whenever `dateFilter` is set. DataViews uses `view.filters` to
	// decide whether Reset is enabled (it requires at least one filter with a
	// value or one non-primary filter — see `ResetFilter` in `dataviews-filters`).
	// Without the synthetic, Reset would be invisible (`opacity: 0`) whenever
	// only the standalone date filter is active. `handleViewChange` strips the
	// synthetic back out before persisting to `useFeesView`, so URL/user_meta
	// never see it.
	const augmentedView = useMemo< View >( () => {
		if ( ! dateFilter ) {
			return view;
		}
		const filters = ( view as ViewTable ).filters ?? [];
		return {
			...view,
			filters: [ ...filters, dateAnchorFilter ],
		};
	}, [ view, dateFilter ] );
	const hasError = Object.keys( error ).length > 0;
	const hasFilters =
		( view.filters ?? [] ).length > 0 || !! view.search || !! dateFilter;
	const isEmpty =
		! isLoading && ! hasError && rows.length === 0 && ! hasFilters;

	// Move focus to the error region and announce when an error surfaces, so
	// keyboard/AT users notice the table disappearing. `role="alert"` on the
	// container takes care of automatic announcement; the focus move handles
	// keyboard context.
	const errorHeadingRef = useRef< HTMLHeadingElement >( null );
	const previousErrorRef = useRef( hasError );
	useEffect( () => {
		if ( hasError && ! previousErrorRef.current ) {
			errorHeadingRef.current?.focus();
		}
		previousErrorRef.current = hasError;
	}, [ hasError ] );

	// Announce "Fees report loaded" to AT users on every loading→ready edge.
	const previousLoadingRef = useRef( isLoading );
	useEffect( () => {
		if ( previousLoadingRef.current && ! isLoading && ! hasError ) {
			speak(
				sprintf(
					/* translators: %d: number of fees loaded into the report table. */
					__( '%d fees loaded.', 'woocommerce-payments' ),
					totalItems
				)
			);
		}
		previousLoadingRef.current = isLoading;
	}, [ isLoading, hasError, totalItems ] );

	const handleViewChange = useCallback(
		( next: View ) => {
			// Strip the synthetic anchor filter before passing the view back
			// out — `useFeesView` / `useFeesData` mustn't see it, and Reset's
			// `filters: []` payload arrives already without it.
			const strippedFilters = (
				( next as ViewTable ).filters ?? []
			).filter( ( f ) => f.field !== dateFilterAnchorFieldId );
			const stripped: View = {
				...next,
				filters: strippedFilters,
			};

			if ( haveFieldsChanged( view.fields, stripped.fields ) ) {
				recordEvent( 'wcpay_reports_view_options_opened', {
					report: 'fees',
				} );
			}
			// DataViews' Reset button calls onChangeView with this exact
			// shape — `{ ...view, page: 1, search: '', filters: [] }`. The
			// trio search-cleared + filters-cleared + page-1 only happens on
			// Reset, so we can use it to mirror "Reset everything" semantics
			// onto our standalone date chip too. The transition guard fires
			// whenever ANY of the resettable inputs (search, DataViews filters,
			// or our standalone date filter) was active beforehand — without
			// it, a Reset payload received against an already-empty view would
			// also match the shape and produce a stray dateFilter clear.
			const isReset =
				stripped.search === '' &&
				strippedFilters.length === 0 &&
				stripped.page === 1 &&
				( view.search !== '' ||
					( view.filters ?? [] ).length > 0 ||
					!! dateFilter );
			if ( isReset && dateFilter ) {
				setDateFilter( undefined );
			}
			setView( stripped );
		},
		[
			dateFilter,
			setDateFilter,
			setView,
			view.fields,
			view.filters,
			view.search,
		]
	);

	const handleDateFilterChange = useCallback(
		( next: DateFilterValue | undefined ) => {
			if ( hasDateFilterChanged( dateFilter, next ) ) {
				recordEvent( 'wcpay_reports_date_range_changed', {
					report: 'fees',
				} );
			}
			setDateFilter( next );
		},
		[ dateFilter, setDateFilter ]
	);

	// Portal target inside DataViews' `.dataviews-filters__container`. The
	// container is gated by `<DataViewsFilters />` (rendered when the field
	// list contains any primary filter — see fees/fields.tsx anchor) AND by
	// `Filters()` (returns null unless at least one filter is visible). The
	// anchor field's `isPrimary: true` keeps both gates open. We append the
	// target at the end of the container's children (React's reconciliation
	// is more tolerant of foreign nodes at the tail) and let CSS `order: -1`
	// float the chip to the visual front of the HStack.
	//
	// We track the wrapper via state (not useRef) so the `useLayoutEffect`
	// below re-runs when FeesReport flips from its empty/error placeholder
	// (which omits the wrapper) into the DataViews path. With a plain ref +
	// `[]` deps, the effect would fire once at mount with `current === null`
	// and never recover.
	const [ dataViewsWrapper, setDataViewsWrapper ] =
		useState< HTMLDivElement | null >( null );
	const [ filterPortalTarget, setFilterPortalTarget ] =
		useState< HTMLElement | null >( null );
	useLayoutEffect( () => {
		if ( ! dataViewsWrapper ) {
			setFilterPortalTarget( null );
			return;
		}

		let attached: HTMLDivElement | null = null;

		const ensureAttached = () => {
			const container = dataViewsWrapper.querySelector< HTMLElement >(
				'.dataviews-filters__container'
			);
			if ( ! container ) {
				if ( attached ) {
					attached = null;
					setFilterPortalTarget( null );
				}
				return;
			}
			if ( attached && attached.parentNode === container ) {
				return;
			}
			const target = document.createElement( 'div' );
			target.className = 'wcpay-date-filter-portal-target';
			container.appendChild( target );
			attached = target;
			setFilterPortalTarget( target );
		};

		ensureAttached();

		const observer = new MutationObserver( ensureAttached );
		observer.observe( dataViewsWrapper, {
			childList: true,
			subtree: true,
		} );

		// After a Reset click, focus stays on the Reset button. DataViews'
		// CSS keeps the now-`aria-disabled` button visible while focused
		// (`.dataviews-filters__reset-button[aria-disabled=true]:focus { opacity: 1 }`),
		// so the button lingers on screen until the user clicks somewhere
		// else. Defer a blur to the next frame so it runs *after* React's
		// reset re-render — without it, the button would remain visibly
		// focused even though there's nothing left to reset.
		const handleResetBlur = ( event: Event ) => {
			const target = event.target as HTMLElement | null;
			if ( ! target?.closest?.( '.dataviews-filters__reset-button' ) ) {
				return;
			}
			requestAnimationFrame( () => {
				const active = document.activeElement as HTMLElement | null;
				if (
					active?.classList.contains(
						'dataviews-filters__reset-button'
					)
				) {
					active.blur();
				}
			} );
		};
		dataViewsWrapper.addEventListener( 'click', handleResetBlur );

		return () => {
			observer.disconnect();
			dataViewsWrapper.removeEventListener( 'click', handleResetBlur );
			if ( attached?.parentNode ) {
				attached.parentNode.removeChild( attached );
			}
		};
	}, [ dataViewsWrapper ] );

	if ( hasError ) {
		return (
			<div
				className="wcpay-reports-state wcpay-reports-state--error"
				role="alert"
				aria-labelledby="wcpay-reports-fees-error"
			>
				<h2
					id="wcpay-reports-fees-error"
					ref={ errorHeadingRef }
					tabIndex={ -1 }
				>
					{ __( 'Fees report unavailable', 'woocommerce-payments' ) }
				</h2>
				<Button variant="secondary" onClick={ onReload }>
					{ __( 'Reload report', 'woocommerce-payments' ) }
				</Button>
			</div>
		);
	}

	if ( isEmpty ) {
		return (
			<div className="wcpay-reports-state wcpay-reports-state--empty">
				<h2>{ __( 'No fees yet', 'woocommerce-payments' ) }</h2>
				<p>
					{ __(
						'Fees will appear here once you start receiving payments.',
						'woocommerce-payments'
					) }
				</p>
			</div>
		);
	}

	const handleExport = () => {
		recordEvent( 'wcpay_reports_export_click', {
			report: 'fees',
			exported_row_count: totalItems,
		} );

		const userEmail = wcpaySettings.currentUserEmail;
		const locale = wcSettings.locale.userLocale;
		const exportRequestURL = getReportsFeesCSVRequestURL( {
			dateBefore: feesQuery.date_before,
			dateAfter: feesQuery.date_after,
			dateBetween: feesQuery.date_between,
			paymentMethodType: feesQuery.payment_method_type,
			type: feesQuery.type,
			search: feesQuery.search,
			orderby: feesQuery.orderby || 'date',
			order: feesQuery.order || 'desc',
			userEmail,
			locale,
		} );

		const confirmThreshold = 10000;
		const confirmMessage = sprintf(
			__(
				"You are about to export %d fees. If you'd like to reduce the size of your export, you can use one or more filters. Would you like to continue?",
				'woocommerce-payments'
			),
			totalItems
		);

		if (
			hasFilters ||
			totalItems < confirmThreshold ||
			window.confirm( confirmMessage )
		) {
			requestReportExport( {
				exportRequestURL,
				exportFileAvailabilityEndpoint: reportsFeesDownloadEndpoint,
				userEmail,
			} );

			createNotice(
				'success',
				sprintf(
					__(
						"🎉 We're processing your export. The file will download automatically and be emailed to %s.",
						'woocommerce-payments'
					),
					userEmail
				)
			);
		}
	};

	return (
		<div className="wcpay-reports-fees">
			<div className="wcpay-reports-fees__toolbar">
				<DownloadButton
					isDisabled={
						isLoading || isExportInProgress || rows.length === 0
					}
					isBusy={ isExportInProgress }
					onClick={ handleExport }
				/>
			</div>
			<div
				className="wcpay-reports-fees__main"
				ref={ setDataViewsWrapper }
			>
				<DataViews
					data={ rows }
					view={ augmentedView }
					onChangeView={ handleViewChange }
					fields={ fields }
					paginationInfo={ { totalItems, totalPages } }
					isLoading={ isLoading }
					defaultLayouts={ { table: {} } }
					search
					searchLabel={ __( 'Search', 'woocommerce-payments' ) }
					getItemId={ ( item ) => item.transaction_id }
				/>
				{ filterPortalTarget &&
					createPortal(
						<DateFilter
							value={ dateFilter }
							onChange={ handleDateFilterChange }
						/>,
						filterPortalTarget
					) }
			</div>
		</div>
	);
};

export default FeesReport;
