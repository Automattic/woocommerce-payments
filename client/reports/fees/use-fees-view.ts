/** @format */

/**
 * External dependencies
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import type { View } from '@wordpress/dataviews/wp';

/**
 * Internal dependencies
 */
import { useFeesUrlSync } from './use-fees-url-sync';
import { useFeesUserPrefs } from './use-fees-user-prefs';

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
			setLocalView( next );
			syncViewToUrl( localView, next );
			persistViewShape( next );
		},
		[ localView, persistViewShape, syncViewToUrl ]
	);

	return [ localView, setView ];
};
