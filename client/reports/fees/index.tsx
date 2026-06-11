/** @format */

/**
 * External dependencies
 */
import React, { useEffect, useId, useMemo, useRef, useState } from 'react';
import { Button } from '@wordpress/components';
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
import { CustomDateFilterPopover } from './custom-date-filter-popover';
import { useDateFilterChipInterceptor } from './use-date-filter-chip-interceptor';
import { resolveFeesDateFilterValue } from './date-filter-values';
import { ReportState } from '../report-state';
import './style.scss';

interface FeesReportProps {
	onReload?: () => void;
}

const customDatePopoverId = 'wcpay-fees-date-filter-popover';
const millisecondsPerDay = 86400000;

const findDateFilter = ( filters: Filter[] = [] ): Filter | undefined =>
	filters.find( ( filter ) => filter.field === 'date' );

const getDateRangeDays = ( view: View ): number | null => {
	const dateFilter = resolveFeesDateFilterValue(
		findDateFilter( view.filters )?.value
	);
	if ( ! dateFilter || dateFilter.operator !== 'between' ) {
		return null;
	}

	const start = new Date( dateFilter.value[ 0 ] ).getTime();
	const end = new Date( dateFilter.value[ 1 ] ).getTime();
	return Math.round( ( end - start ) / millisecondsPerDay );
};

export const FeesReport = ( {
	onReload = () => undefined,
}: FeesReportProps ): JSX.Element => {
	const [ view, setView ] = useFeesView();
	// Stable reference date so date-filter telemetry presets are matched
	// against a single `now` for the lifetime of the report, even across a
	// day boundary.
	const stableDateFilterNow = useRef( new Date() ).current;
	const [ dataViewsContainer, setDataViewsContainer ] =
		useState< HTMLDivElement | null >( null );
	const initialEmptyHeadingId = useId();
	const initialEmptyDescriptionId = useId();
	const filteredEmptyHeadingId = useId();
	const filteredEmptyDescriptionId = useId();
	const {
		rows,
		totalItems,
		totalPages,
		dateElements,
		methodElements,
		typeElements,
		isLoading,
		error,
	} = useFeesData( view );

	const {
		anchor: customDateAnchor,
		isPopoverOpen: isCustomDatePopoverOpen,
		initialValue: customDateInitialValue,
		onPopoverChange: changeCustomDateFilter,
		onPopoverClose: closeCustomDatePopover,
		captureHandlers: dateFilterCaptureHandlers,
	} = useDateFilterChipInterceptor( {
		container: dataViewsContainer,
		view,
		setView,
		popoverId: customDatePopoverId,
		now: stableDateFilterNow,
	} );

	const fields = useMemo(
		() =>
			getFeesFields( {
				dateElements,
				methodElements,
				typeElements,
			} ),
		[ dateElements, methodElements, typeElements ]
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
				speak( message );
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
				ref={ setDataViewsContainer }
				tabIndex={ -1 }
				{ ...dateFilterCaptureHandlers }
			>
				<DataViews
					data={ rows }
					view={ view }
					onChangeView={ setView }
					fields={ fields }
					paginationInfo={ { totalItems, totalPages } }
					isLoading={ isLoading }
					defaultLayouts={ { table: {} } }
					search
					searchLabel={ __( 'Search fees', 'woocommerce-payments' ) }
					getItemId={ ( item ) => item.transaction_id }
				/>
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
				{ isCustomDatePopoverOpen && (
					<CustomDateFilterPopover
						anchor={ customDateAnchor }
						fallbackFocus={ dataViewsContainer }
						id={ customDatePopoverId }
						initialValue={ customDateInitialValue }
						onChange={ changeCustomDateFilter }
						onClose={ closeCustomDatePopover }
					/>
				) }
			</div>
		</div>
	);
};

export default FeesReport;
