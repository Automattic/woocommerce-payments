/** @format */

/**
 * External dependencies
 */
import React, { useEffect, useId, useMemo, useRef } from 'react';
import { Button, Spinner } from '@wordpress/components';
import { calendar } from '@wordpress/icons';
import { __, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { DataViews, type Filter, type View } from '@wordpress/dataviews/wp';
import { recordEvent } from 'tracks';

/**
 * Internal dependencies
 */
import { useFeesView } from './use-fees-view';
import { useFeesData } from './use-fees-data';
import { getFeesFields } from './fields';
import { getFeesDateFilterRangeDays } from './date-filter-values';
import { ReportState } from '../report-state';
import './style.scss';

interface FeesReportProps {
	onReload?: () => void;
}

const findDateFilter = ( filters: Filter[] = [] ): Filter | undefined =>
	filters.find( ( filter ) => filter.field === 'date' );

const getDateRangeDays = ( view: View ): number | null =>
	getFeesDateFilterRangeDays( findDateFilter( view.filters ) );

// DataViews.Footer is callable in the runtime version used here, but upstream
// types do not expose a callable compound component shape yet.
const DataViewsFooter = DataViews.Footer as () => JSX.Element | null;

export const FeesReport = ( {
	onReload = () => undefined,
}: FeesReportProps ): JSX.Element => {
	// The report feedback survey is intentionally Balance-only.
	const [ view, setView ] = useFeesView();
	const initialEmptyHeadingId = useId();
	const initialEmptyDescriptionId = useId();
	const filteredEmptyHeadingId = useId();
	const filteredEmptyDescriptionId = useId();
	const {
		rows,
		totalItems,
		totalPages,
		methodElements,
		typeElements,
		isLoading,
		error,
	} = useFeesData( view );

	const fields = useMemo(
		() =>
			getFeesFields( {
				methodElements,
				typeElements,
			} ),
		[ methodElements, typeElements ]
	);
	const hasError = Object.keys( error ).length > 0;
	const hasFilters = ( view.filters ?? [] ).length > 0 || !! view.search;
	const hasNoRows = ! isLoading && ! hasError && rows.length === 0;
	const isInitialEmpty = hasNoRows && ! hasFilters;
	const isFilteredEmpty = hasNoRows && hasFilters;
	const rangeDays = useMemo( () => getDateRangeDays( view ), [ view ] );

	// Move focus to the error region and announce when an error surfaces, so
	// keyboard/AT users notice the table disappearing. `role="alert"` on the
	// container takes care of automatic announcement; the focus move handles
	// keyboard context.
	const errorHeadingRef = useRef< HTMLHeadingElement >( null );
	const previousErrorRef = useRef( hasError );
	const previousLoadingRef = useRef( isLoading );
	const previousLoadingAnnouncementRef = useRef( false );
	useEffect( () => {
		const reachedErrorTerminal =
			hasError &&
			! isLoading &&
			( ! previousErrorRef.current || previousLoadingRef.current );

		if ( reachedErrorTerminal ) {
			recordEvent( 'wcpay_reports_fees_load_error', {
				has_filters: hasFilters,
				range_days: rangeDays,
			} );
		}

		if ( hasError && ! previousErrorRef.current ) {
			errorHeadingRef.current?.focus();
		}

		previousErrorRef.current = hasError;
	}, [ hasError, hasFilters, isLoading, rangeDays ] );

	useEffect( () => {
		if ( isLoading && ! previousLoadingAnnouncementRef.current ) {
			speak( __( 'Loading fees', 'woocommerce-payments' ), 'polite' );
		}
		previousLoadingAnnouncementRef.current = isLoading;
	}, [ isLoading ] );

	// Announce "Fees report loaded" to AT users on every loading→ready edge.
	// Debounced (500ms) and de-duplicated so rapid filter changes — which can
	// cause loading→ready→loading→ready bursts — collapse into a single
	// announcement instead of spamming AT users.
	const speakTimerRef = useRef< ReturnType< typeof setTimeout > | null >(
		null
	);
	const lastSpokenRef = useRef< string | null >( null );
	useEffect( () => {
		if ( previousLoadingRef.current && ! isLoading && ! hasError ) {
			recordEvent( 'wcpay_reports_fees_load_success', {
				total_items: totalItems,
				has_filters: hasFilters,
				is_initial_empty: isInitialEmpty,
				is_filtered_empty: isFilteredEmpty,
				range_days: rangeDays,
			} );

			const message = sprintf(
				/* translators: %d: number of fees loaded into the report table. */
				__( '%d fees loaded.', 'woocommerce-payments' ),
				totalItems
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
				speak( message, 'polite' );
			}, 500 );
		}
		previousLoadingRef.current = isLoading;
	}, [
		hasError,
		hasFilters,
		isFilteredEmpty,
		isInitialEmpty,
		isLoading,
		rangeDays,
		totalItems,
	] );

	useEffect(
		() => () => {
			if ( speakTimerRef.current ) {
				clearTimeout( speakTimerRef.current );
				speakTimerRef.current = null;
			}
		},
		[]
	);

	if ( hasError ) {
		return (
			<ReportState
				title={ __(
					'Fees report unavailable',
					'woocommerce-payments'
				) }
				description={
					<>
						<span>
							{ __(
								"We couldn't load your fees data.",
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
							recordEvent( 'wcpay_reports_fees_reload_click', {
								range_days: rangeDays,
							} );
							onReload();
						} }
					>
						{ __( 'Reload report', 'woocommerce-payments' ) }
					</Button>
				}
				icon={ calendar }
				className="wcpay-reports-state--error wcpay-reports-state--fees-error"
				descriptionId="wcpay-reports-fees-error-description"
				headingId="wcpay-reports-fees-error"
				headingRef={ errorHeadingRef }
				headingTabIndex={ -1 }
				role="alert"
			/>
		);
	}

	if ( isInitialEmpty ) {
		return (
			/* role="status" is implicitly aria-live="polite". Safe here because the
			   empty states do not shift focus — keep this in sync if you add focus
			   management later. */
			<ReportState
				title={ __( 'No fees yet', 'woocommerce-payments' ) }
				className="wcpay-reports-state--empty wcpay-reports-state--fees-empty"
				description={ __(
					'Fees will appear here once you start receiving payments.',
					'woocommerce-payments'
				) }
				icon={ calendar }
				descriptionId={ initialEmptyDescriptionId }
				headingId={ initialEmptyHeadingId }
				role="status"
			/>
		);
	}

	return (
		<div className="wcpay-reports-fees">
			<div
				className={
					isFilteredEmpty
						? 'wcpay-reports-fees__main wcpay-reports-fees__main--filtered-empty'
						: 'wcpay-reports-fees__main'
				}
				tabIndex={ -1 }
			>
				<DataViews
					data={ rows }
					view={ view }
					onChangeView={ setView }
					fields={ fields }
					paginationInfo={ { totalItems, totalPages } }
					isLoading={ isLoading }
					empty={
						isLoading ? (
							<div
								className="wcpay-reports-fees__loading-empty"
								aria-hidden="true"
							>
								<Spinner />
							</div>
						) : undefined
					}
					defaultLayouts={ { table: {} } }
					getItemId={ ( item ) => item.transaction_id }
				>
					<div
						key="view-actions"
						className="wcpay-reports-fees__view-actions"
					>
						<DataViews.Search
							label={ __(
								'Search fees',
								'woocommerce-payments'
							) }
						/>
						<DataViews.FiltersToggle />
						<DataViews.ViewConfig />
					</div>
					<DataViews.FiltersToggled
						key="filters-toggled"
						className="dataviews-filters__container"
					/>
					<DataViews.Layout key="layout" />
					<DataViewsFooter key="footer" />
				</DataViews>
				{ isFilteredEmpty && (
					/* role="status" is implicitly aria-live="polite". Safe here because the
					   empty states do not shift focus — keep this in sync if you add focus
					   management later. */
					<ReportState
						title={ __(
							'No fees to display',
							'woocommerce-payments'
						) }
						className="wcpay-reports-state--empty wcpay-reports-state--fees-empty"
						description={ __(
							'Fees will appear here.',
							'woocommerce-payments'
						) }
						icon={ calendar }
						descriptionId={ filteredEmptyDescriptionId }
						headingId={ filteredEmptyHeadingId }
						role="status"
					/>
				) }
			</div>
		</div>
	);
};

export default FeesReport;
