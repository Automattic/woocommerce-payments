/** @format */

/**
 * External dependencies
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import type { Filter, View } from '@wordpress/dataviews/wp';
import { recordEvent } from 'tracks';

/**
 * Internal dependencies
 */
import { matchPreset } from 'wcpay/reports/date-filter/presets';
import { useFeesUrlSync } from './use-fees-url-sync';
import { useFeesUserPrefs } from './use-fees-user-prefs';
import {
	getFeesDateFilterRangeDays,
	getFeesDateFilterValue,
} from './date-filter-values';

const searchTrackingDebounceMs = 500;

const getTrackedFilterField = ( field: string ): string =>
	field === 'payment_method' ? 'payment_method_type' : field;

const dateFilterField = 'date';

const findFilterByField = (
	filters: Filter[] | undefined,
	field: string
): Filter | undefined => filters?.find( ( filter ) => filter.field === field );

const stringifyFilterValue = ( value: unknown ): string | undefined => {
	try {
		return JSON.stringify( value );
	} catch {
		return undefined;
	}
};

const areFilterValuesEqual = ( previous: unknown, next: unknown ): boolean => {
	if ( previous === next ) {
		return true;
	}

	const previousValue = stringifyFilterValue( previous );
	const nextValue = stringifyFilterValue( next );
	return previousValue !== undefined && previousValue === nextValue;
};

const getTrackableFilterField = ( filter: Filter ): string | undefined => {
	if ( filter.field === 'date' ) {
		return undefined;
	}

	if (
		filter.field === 'payment_method' &&
		typeof filter.value === 'string' &&
		filter.value.trim() !== ''
	) {
		return getTrackedFilterField( filter.field );
	}

	if ( filter.field === 'type' && typeof filter.value === 'string' ) {
		const value = filter.value.trim();
		if ( value !== '' && ! value.includes( ',' ) ) {
			return getTrackedFilterField( filter.field );
		}
	}

	return undefined;
};

interface SearchTrackingControls {
	clearPendingSearchTracking: () => void;
	scheduleSearchTracking: ( search: string ) => void;
}

const trackViewChange = (
	previous: View,
	next: View,
	{
		clearPendingSearchTracking,
		scheduleSearchTracking,
	}: SearchTrackingControls,
	now: Date
): void => {
	const previousSearch = previous.search ?? '';
	const nextSearch = next.search ?? '';
	if ( nextSearch !== previousSearch ) {
		clearPendingSearchTracking();
		if ( nextSearch ) {
			scheduleSearchTracking( nextSearch );
		}
	}

	const previousDateFilter = findFilterByField(
		previous.filters,
		dateFilterField
	);
	const nextDateFilter = findFilterByField( next.filters, dateFilterField );
	const previousDateValue = getFeesDateFilterValue( previousDateFilter );
	const nextDateValue = getFeesDateFilterValue( nextDateFilter );
	if ( previousDateValue && ! nextDateValue ) {
		recordEvent( 'wcpay_reports_fees_date_filter_change', {
			preset: 'reset',
			range_days: null,
			is_initial_apply: false,
		} );
	} else if (
		nextDateValue &&
		! areFilterValuesEqual( previousDateValue, nextDateValue )
	) {
		recordEvent( 'wcpay_reports_fees_date_filter_change', {
			preset: matchPreset( nextDateValue, now ),
			range_days: getFeesDateFilterRangeDays( nextDateFilter ),
			is_initial_apply: ! previousDateValue,
		} );
	}

	( next.filters ?? [] ).forEach( ( filter ) => {
		const filterField = getTrackableFilterField( filter );
		if ( ! filterField ) {
			return;
		}

		const previousFilter = findFilterByField(
			previous.filters,
			filter.field
		);
		if ( areFilterValuesEqual( previousFilter?.value, filter.value ) ) {
			return;
		}

		recordEvent( 'wcpay_reports_fees_filter_change', {
			filter_field: filterField,
			had_previous_value: previousFilter?.value !== undefined,
		} );
	} );
};

/**
 * Hook that owns the Fees report's DataViews `view`, bidirectionally synced
 * with the URL (sort, page, search, filters) and `user_meta` (fields, layout,
 * perPage). Returns the current view and a setter.
 */
export const useFeesView = (): [ View, ( next: View ) => void ] => {
	const { persisted, hasLoadedPersisted, persistViewShape } =
		useFeesUserPrefs();
	const { derivedView, syncViewToUrl, urlVersion } =
		useFeesUrlSync( persisted );
	const searchTrackingTimerRef = useRef< ReturnType<
		typeof setTimeout
	> | null >( null );
	const stableDateFilterNow = useRef( new Date() ).current;
	const clearPendingSearchTracking = useCallback( () => {
		if ( searchTrackingTimerRef.current ) {
			clearTimeout( searchTrackingTimerRef.current );
			searchTrackingTimerRef.current = null;
		}
	}, [] );
	const scheduleSearchTracking = useCallback( ( search: string ) => {
		searchTrackingTimerRef.current = setTimeout( () => {
			recordEvent( 'wcpay_reports_fees_search', {
				search_length: search.length,
			} );
			searchTrackingTimerRef.current = null;
		}, searchTrackingDebounceMs );
	}, [] );

	useEffect(
		() => () => clearPendingSearchTracking(),
		[ clearPendingSearchTracking ]
	);

	// Hold the view in local React state so DataViews can stage in-progress
	// filters (a method/type filter is added before its value is picked — the
	// chip needs to live on the page long enough for the user to pick a value
	// from the popover). The URL is the source of truth for applied filters,
	// but it can't represent a value-less filter chip, so URL-only derivation
	// would erase the chip on the very next render.
	const [ localView, setLocalView ] = useState< View >( derivedView );

	// Re-seed local state only on external URL navigation or the first time
	// persisted user_meta loads. Own setView writes can update user_meta, which
	// changes the derived view identity, but that must not erase staged chips.
	const lastUrlVersionRef = useRef( urlVersion );
	const wasLoadedRef = useRef( hasLoadedPersisted );
	useEffect( () => {
		const urlChanged = lastUrlVersionRef.current !== urlVersion;
		const justLoaded = ! wasLoadedRef.current && hasLoadedPersisted;

		lastUrlVersionRef.current = urlVersion;
		wasLoadedRef.current = hasLoadedPersisted;

		if ( urlChanged || justLoaded ) {
			setLocalView( derivedView );
		}
	}, [ urlVersion, hasLoadedPersisted, derivedView ] );

	const setView = useCallback(
		( next: View ) => {
			trackViewChange(
				localView,
				next,
				{
					clearPendingSearchTracking,
					scheduleSearchTracking,
				},
				stableDateFilterNow
			);
			setLocalView( next );
			syncViewToUrl( localView, next );
			persistViewShape( next );
		},
		[
			clearPendingSearchTracking,
			localView,
			persistViewShape,
			scheduleSearchTracking,
			stableDateFilterNow,
			syncViewToUrl,
		]
	);

	return [ localView, setView ];
};
