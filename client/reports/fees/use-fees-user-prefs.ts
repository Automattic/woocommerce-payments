/** @format */

/**
 * External dependencies
 */
import { useCallback, useEffect, useRef } from 'react';
import { useUserPreferences } from '@woocommerce/data';
import type { View, ViewTable } from '@wordpress/dataviews/wp';

/**
 * Internal dependencies
 */
import { feesViewUserMetaKey, PersistedFeesView, FeesFieldId } from './view';

const persistDebounceMs = 750;

const isPersistedShapeEqual = (
	a: PersistedFeesView | undefined,
	b: PersistedFeesView
): boolean => a !== undefined && JSON.stringify( a ) === JSON.stringify( b );

interface UseFeesUserPrefsResult {
	persisted: PersistedFeesView | undefined;
	hasLoadedPersisted: boolean;
	persistViewShape: ( next: View ) => void;
}

export const useFeesUserPrefs = (): UseFeesUserPrefsResult => {
	const { updateUserPreferences, ...userPrefs } = useUserPreferences();
	const prefs = userPrefs as unknown as Record< string, unknown >;
	const persisted = prefs[ feesViewUserMetaKey ] as
		| PersistedFeesView
		| undefined;
	// `undefined` means user_meta hasn't loaded yet; `null`-ish empty string is
	// what wp-data returns once the resolver finishes with no stored value.
	const hasLoadedPersisted = feesViewUserMetaKey in prefs;

	const persistDebounceTimerRef = useRef< ReturnType<
		typeof setTimeout
	> | null >( null );
	const clearPendingPersistUpdate = useCallback( () => {
		if ( persistDebounceTimerRef.current ) {
			clearTimeout( persistDebounceTimerRef.current );
			persistDebounceTimerRef.current = null;
		}
	}, [] );
	useEffect( () => clearPendingPersistUpdate, [ clearPendingPersistUpdate ] );

	const persistViewShape = useCallback(
		( next: View ) => {
			const nextPersisted: PersistedFeesView = {
				fields: ( next.fields ?? [] ) as FeesFieldId[],
				perPage: next.perPage,
				layout: ( next as ViewTable ).layout,
			};

			// Skip the write until user_meta has actually loaded; otherwise
			// the first interaction on a fresh page write the default shape
			// over whatever the user previously stored.
			if (
				! hasLoadedPersisted ||
				isPersistedShapeEqual( persisted, nextPersisted )
			) {
				return;
			}

			// Debounce: a burst of view changes (e.g. toggling several columns
			// or rapidly changing perPage) collapses into a single REST write
			// of the final shape.
			clearPendingPersistUpdate();
			persistDebounceTimerRef.current = setTimeout( () => {
				updateUserPreferences( {
					[ feesViewUserMetaKey ]: nextPersisted,
				} );
				persistDebounceTimerRef.current = null;
			}, persistDebounceMs );
		},
		[
			clearPendingPersistUpdate,
			hasLoadedPersisted,
			persisted,
			updateUserPreferences,
		]
	);

	return { persisted, hasLoadedPersisted, persistViewShape };
};
