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
import { useFeesUrlSync } from './use-fees-url-sync';
import { useFeesUserPrefs } from './use-fees-user-prefs';

const getTrackedFilterField = ( field: string ): string =>
	field === 'payment_method' ? 'payment_method_type' : field;

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

const trackViewChange = ( previous: View, next: View ): void => {
	const previousSearch = previous.search ?? '';
	const nextSearch = next.search ?? '';
	if ( nextSearch && nextSearch !== previousSearch ) {
		recordEvent( 'wcpay_reports_fees_search', {
			search_length: nextSearch.length,
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
			trackViewChange( localView, next );
			setLocalView( next );
			syncViewToUrl( localView, next );
			persistViewShape( next );
		},
		[ localView, persistViewShape, syncViewToUrl ]
	);

	return [ localView, setView ];
};
