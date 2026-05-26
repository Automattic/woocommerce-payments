/** @format */

/**
 * External dependencies
 */
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { getQuery, updateQueryString } from '@woocommerce/navigation';
import type { View, ViewTable, Filter } from '@wordpress/dataviews/wp';

/**
 * Internal dependencies
 */
import { defaultPerPage, getDefaultFeesView, PersistedFeesView } from './view';
import {
	parseFeesDateFilterValueFromQuery,
	serializeFeesDateFilterValueToQuery,
} from './date-filter-values';

const reportsPath = '/payments/reports';
const searchDebounceMs = 500;

const parseIntOr = ( value: unknown, fallback: number ): number => {
	const n = parseInt( String( value ?? '' ), 10 );
	return Number.isNaN( n ) ? fallback : n;
};

const getSingleQueryValue = ( value: unknown ): string | undefined => {
	if ( typeof value !== 'string' || value === '' ) {
		return undefined;
	}

	const trimmed = value.trim();
	if ( trimmed === '' || trimmed.includes( ',' ) ) {
		return undefined;
	}

	return trimmed;
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
		const value = getSingleQueryValue( query.type );
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
			params.type = getSingleQueryValue( filter.value );
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

// Strip the params that are debounced or auto-reset by a search transition,
// so the "do non-search params differ?" comparison upstream isn't fooled by
// the search field flush or its page=1 side effect.
const withoutTransientParams = (
	params: Record< string, unknown >,
	dropPagedReset = false
): Record< string, unknown > => {
	const rest = { ...params };
	delete rest.search;
	if ( dropPagedReset ) {
		delete rest.paged;
	}
	return rest;
};

const areQueryParamsEqual = (
	a: Record< string, unknown >,
	b: Record< string, unknown >
): boolean => JSON.stringify( a ) === JSON.stringify( b );

interface UseFeesUrlSyncResult {
	derivedView: View;
	syncViewToUrl: ( current: View, next: View ) => void;
	urlVersion: number;
}

export const useFeesUrlSync = (
	persisted: PersistedFeesView | undefined
): UseFeesUrlSyncResult => {
	const [ navTick, setNavTick ] = useState( 0 );
	const bumpNavTick = useCallback( () => setNavTick( ( t ) => t + 1 ), [] );
	useEffect( () => {
		window.addEventListener( 'popstate', bumpNavTick );
		return () => window.removeEventListener( 'popstate', bumpNavTick );
	}, [ bumpNavTick ] );

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

	const searchDebounceTimerRef = useRef< ReturnType<
		typeof setTimeout
	> | null >( null );
	const clearPendingSearchUpdate = useCallback( () => {
		if ( searchDebounceTimerRef.current ) {
			clearTimeout( searchDebounceTimerRef.current );
			searchDebounceTimerRef.current = null;
		}
	}, [] );
	useEffect( () => clearPendingSearchUpdate, [ clearPendingSearchUpdate ] );

	const syncViewToUrl = useCallback(
		( current: View, next: View ) => {
			const currentQueryParams = buildUrlQueryParams( current );
			const nextQueryParams = buildUrlQueryParams( next );
			const hasSearchChange = ! areQueryParamsEqual(
				{ search: currentQueryParams.search },
				{ search: nextQueryParams.search }
			);
			const isSearchPageReset =
				hasSearchChange && nextQueryParams.paged === '1';
			const hasImmediateUrlChange = ! areQueryParamsEqual(
				withoutTransientParams( currentQueryParams, isSearchPageReset ),
				withoutTransientParams( nextQueryParams, isSearchPageReset )
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
		},
		[ clearPendingSearchUpdate ]
	);

	return { derivedView, syncViewToUrl, urlVersion: navTick };
};
