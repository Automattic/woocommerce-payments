/** @format */

/**
 * External dependencies
 */
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { getQuery, updateQueryString } from '@woocommerce/navigation';
import { useUserPreferences } from '@woocommerce/data';
import type { View, ViewTable, Filter } from '@wordpress/dataviews';

/**
 * Internal dependencies
 */
import {
	defaultPerPage,
	feesViewUserMetaKey,
	getDefaultFeesView,
	PersistedFeesView,
	FeesFieldId,
} from './view';
import {
	parseFeesDateFilterValueFromQuery,
	serializeFeesDateFilterValueToQuery,
} from './date-filter-values';

const reportsPath = '/payments/reports';
const legacyHiddenColumnsKey = 'wc_payments_reports_fees_hidden_columns';
const searchDebounceMs = 500;

const parseIntOr = ( value: unknown, fallback: number ): number => {
	const n = parseInt( String( value ?? '' ), 10 );
	return Number.isNaN( n ) ? fallback : n;
};

const getFirstQueryValue = ( value: unknown ): string | undefined => {
	if ( Array.isArray( value ) ) {
		return getFirstQueryValue( value[ 0 ] );
	}

	if ( typeof value !== 'string' || value === '' ) {
		return undefined;
	}

	return value.split( ',' )[ 0 ] || undefined;
};

const buildFiltersFromQuery = (
	query: Record< string, unknown >
): Filter[] => {
	const filters: Filter[] = [];
	const dateValue = parseFeesDateFilterValueFromQuery( query );

	if ( dateValue ) {
		filters.push( {
			field: 'date',
			operator: 'is',
			value: dateValue,
		} );
	}

	if ( query.payment_method_type ) {
		filters.push( {
			field: 'payment_method',
			operator: 'is',
			value: query.payment_method_type as string,
		} );
	}

	if ( query.type ) {
		const value = getFirstQueryValue( query.type );
		if ( ! value ) {
			return filters;
		}
		filters.push( {
			field: 'type',
			operator: 'is',
			value,
		} );
	}

	return filters;
};

const isPersistedShapeEqual = (
	a: PersistedFeesView | undefined,
	b: PersistedFeesView
): boolean => a !== undefined && JSON.stringify( a ) === JSON.stringify( b );

/**
 * Translate active filters into a URL-query patch that clears stale filter
 * keys (sets them to `undefined`) and writes only the keys that are currently
 * active. Always returns the full filter-query object — every key not set by
 * an active filter is explicitly `undefined` so `updateQueryString` removes it.
 */
const buildFilterQueryParams = (
	filters: Filter[]
): Record< string, unknown > => {
	const params: Record< string, unknown > = {
		date_preset: undefined,
		date_between: undefined,
		date_before: undefined,
		date_after: undefined,
		payment_method_type: undefined,
		type: undefined,
	};

	for ( const filter of filters ) {
		if ( filter.field === 'date' ) {
			Object.assign(
				params,
				serializeFeesDateFilterValueToQuery( filter.value )
			);
		} else if ( filter.field === 'payment_method' ) {
			params.payment_method_type = filter.value;
		} else if ( filter.field === 'type' ) {
			params.type = filter.value;
		}
	}

	return params;
};

const buildUrlQueryParams = ( view: View ): Record< string, unknown > => ( {
	orderby: view.sort?.field,
	order: view.sort?.direction,
	paged: view.page ? String( view.page ) : undefined,
	per_page: view.perPage ? String( view.perPage ) : undefined,
	search: view.search ? [ view.search ] : undefined,
	...buildFilterQueryParams( view.filters ?? [] ),
} );

const withoutSearchParam = (
	params: Record< string, unknown >,
	ignoreSearchPageReset = false
): Record< string, unknown > => {
	const rest = { ...params };
	delete rest.search;
	if ( ignoreSearchPageReset ) {
		delete rest.paged;
	}
	return rest;
};

const areQueryParamsEqual = (
	a: Record< string, unknown >,
	b: Record< string, unknown >
): boolean => JSON.stringify( a ) === JSON.stringify( b );

/**
 * Hook that owns the Fees report's DataViews `view`, bidirectionally synced
 * with the URL (sort, page, search, filters) and `user_meta` (fields, layout,
 * perPage). Returns the current view and a setter.
 */
export const useFeesView = (): [ View, ( next: View ) => void ] => {
	const { updateUserPreferences, ...userPrefs } = useUserPreferences();
	const prefs = userPrefs as unknown as Record< string, unknown >;
	const persisted = prefs[ feesViewUserMetaKey ] as
		| PersistedFeesView
		| undefined;
	// `undefined` means user_meta hasn't loaded yet; `null`-ish empty string is
	// what wp-data returns once the resolver finishes with no stored value.
	const hasLoadedPersisted = feesViewUserMetaKey in prefs;

	// Browser back/forward — and our own pushState writes — change the URL
	// without remounting; we bump this tick to force the view memo to re-read
	// `getQuery()` instead of going stale.
	const [ navTick, setNavTick ] = useState( 0 );
	const bumpNavTick = useCallback( () => setNavTick( ( t ) => t + 1 ), [] );
	useEffect( () => {
		window.addEventListener( 'popstate', bumpNavTick );
		return () => window.removeEventListener( 'popstate', bumpNavTick );
	}, [ bumpNavTick ] );

	// One-time migration: if the new key isn't set but the legacy
	// `wc_payments_reports_fees_hidden_columns` exists, derive an initial
	// `fields` list from the legacy hidden columns and persist under the
	// new key. Fires once per session at most.
	const migrationAttemptedRef = useRef( false );
	useEffect( () => {
		if ( migrationAttemptedRef.current || ! hasLoadedPersisted ) {
			return;
		}
		migrationAttemptedRef.current = true;
		if ( persisted ) {
			return;
		}
		const legacy = prefs[ legacyHiddenColumnsKey ];
		if ( ! Array.isArray( legacy ) || legacy.length === 0 ) {
			return;
		}
		const defaultView = getDefaultFeesView() as ViewTable;
		const migratedFields = ( defaultView.fields ?? [] ).filter(
			( field ) => ! legacy.includes( field )
		) as FeesFieldId[];
		updateUserPreferences( {
			[ feesViewUserMetaKey ]: {
				fields: migratedFields,
				perPage: defaultPerPage,
				layout: defaultView.layout,
			},
		} );
	}, [ hasLoadedPersisted, persisted, prefs, updateUserPreferences ] );

	const derivedView: View = useMemo< ViewTable >( () => {
		const query = getQuery() as Record< string, unknown >;
		const defaultView = getDefaultFeesView() as ViewTable;
		return {
			...defaultView,
			fields: persisted?.fields ?? defaultView.fields,
			perPage: parseIntOr(
				query.per_page,
				persisted?.perPage ?? defaultPerPage
			),
			page: parseIntOr( query.paged, 1 ),
			sort: {
				field: ( query.orderby as string ) ?? 'date',
				direction:
					( query.order as 'asc' | 'desc' ) ??
					defaultView.sort?.direction ??
					'desc',
			},
			search: getFirstQueryValue( query.search ) ?? '',
			filters: buildFiltersFromQuery( query ),
			layout: persisted?.layout ?? defaultView.layout,
		};
		// navTick forces re-derive when the URL changes externally (popstate).
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ persisted, navTick ] );

	// Hold the view in local React state so DataViews can stage in-progress
	// filters (a method/type filter is added before its value is picked — the
	// chip needs to live on the page long enough for the user to pick a value
	// from the popover). The URL is the source of truth for *applied* filters,
	// but it can't represent a value-less filter chip, so URL-only derivation
	// would erase the chip on the very next render.
	const [ localView, setLocalView ] = useState< View >( derivedView );

	// Re-seed local state only on external triggers: a popstate (which bumps
	// navTick) or the first time persisted user_meta loads. Our own setView
	// writes call `updateUserPreferences`, which changes `persisted` and thus
	// the `derivedView` identity — but we must NOT resync from that, otherwise
	// a value-less filter chip we just staged in local state gets erased on
	// the same render.
	const lastNavTickRef = useRef( navTick );
	const wasLoadedRef = useRef( hasLoadedPersisted );
	useEffect( () => {
		const navChanged = lastNavTickRef.current !== navTick;
		const justLoaded = ! wasLoadedRef.current && hasLoadedPersisted;
		if ( navChanged || justLoaded ) {
			lastNavTickRef.current = navTick;
			wasLoadedRef.current = hasLoadedPersisted;
			setLocalView( derivedView );
		}
	}, [ navTick, hasLoadedPersisted, derivedView ] );

	const searchDebounceTimerRef = useRef< ReturnType<
		typeof setTimeout
	> | null >( null );
	const clearPendingSearchUpdate = useCallback( () => {
		if ( searchDebounceTimerRef.current ) {
			clearTimeout( searchDebounceTimerRef.current );
			searchDebounceTimerRef.current = null;
		}
	}, [] );
	useEffect(
		() => () => {
			clearPendingSearchUpdate();
		},
		[ clearPendingSearchUpdate ]
	);

	const setView = useCallback(
		( next: View ) => {
			setLocalView( next );

			const currentQueryParams = buildUrlQueryParams( localView );
			const nextQueryParams = buildUrlQueryParams( next );
			const hasSearchChange = ! areQueryParamsEqual(
				{ search: currentQueryParams.search },
				{ search: nextQueryParams.search }
			);
			const isSearchPageReset =
				hasSearchChange && nextQueryParams.paged === '1';
			const hasImmediateUrlChange = ! areQueryParamsEqual(
				withoutSearchParam( currentQueryParams, isSearchPageReset ),
				withoutSearchParam( nextQueryParams, isSearchPageReset )
			);

			clearPendingSearchUpdate();
			if ( hasImmediateUrlChange ) {
				updateQueryString( nextQueryParams, reportsPath );
			} else if ( hasSearchChange ) {
				searchDebounceTimerRef.current = setTimeout( () => {
					updateQueryString( nextQueryParams, reportsPath );
					searchDebounceTimerRef.current = null;
				}, searchDebounceMs );
			}

			const nextPersisted: PersistedFeesView = {
				fields: ( next.fields ?? [] ) as FeesFieldId[],
				perPage: next.perPage,
				layout: ( next as ViewTable ).layout,
			};

			// Skip the write until user_meta has actually loaded; otherwise
			// the first interaction on a fresh page write the default shape
			// over whatever the user previously stored.
			if (
				hasLoadedPersisted &&
				! isPersistedShapeEqual( persisted, nextPersisted )
			) {
				updateUserPreferences( {
					[ feesViewUserMetaKey ]: nextPersisted,
				} );
			}
		},
		[
			clearPendingSearchUpdate,
			hasLoadedPersisted,
			localView,
			persisted,
			updateUserPreferences,
		]
	);

	const view = localView;

	return [ view, setView ];
};
