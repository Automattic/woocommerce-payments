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

const reportsPath = '/payments/reports';
const legacyHiddenColumnsKey = 'wc_payments_reports_fees_hidden_columns';

const parseIntOr = ( value: unknown, fallback: number ): number => {
	const n = parseInt( String( value ?? '' ), 10 );
	return Number.isNaN( n ) ? fallback : n;
};

type DateOperator = 'between' | 'before' | 'after';

const dateOperatorByQueryKey: Record< string, DateOperator > = {
	date_between: 'between',
	date_before: 'before',
	date_after: 'after',
};

const queryKeyByDateOperator: Record< DateOperator, string > = {
	between: 'date_between',
	before: 'date_before',
	after: 'date_after',
};

const buildFiltersFromQuery = (
	query: Record< string, unknown >
): Filter[] => {
	const filters: Filter[] = [];

	for ( const [ key, op ] of Object.entries( dateOperatorByQueryKey ) ) {
		if ( query[ key ] ) {
			filters.push( {
				field: 'date',
				operator: op as Filter[ 'operator' ],
				value: query[ key ] as string | string[],
			} );
			break;
		}
	}

	if ( query.payment_method_type ) {
		filters.push( {
			field: 'payment_method',
			operator: 'is',
			value: query.payment_method_type as string,
		} );
	}

	if ( query.type ) {
		const value = Array.isArray( query.type )
			? query.type
			: [ query.type as string ];
		filters.push( {
			field: 'type',
			operator: 'isAny',
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
 * Translate active filters into a URL-query patch that clears stale date and
 * filter keys (sets them to `undefined`) and writes only the keys that are
 * currently active. Always returns a full 5-key object — every key not set by
 * an active filter is explicitly `undefined` so `updateQueryString` removes it.
 */
const buildFilterQueryParams = (
	filters: Filter[]
): Record< string, unknown > => {
	const params: Record< string, unknown > = {
		date_between: undefined,
		date_before: undefined,
		date_after: undefined,
		payment_method_type: undefined,
		type: undefined,
	};

	for ( const filter of filters ) {
		const op = filter.operator as string;
		if ( filter.field === 'date' && op in queryKeyByDateOperator ) {
			params[ queryKeyByDateOperator[ op as DateOperator ] ] =
				filter.value;
		} else if ( filter.field === 'payment_method' ) {
			params.payment_method_type = filter.value;
		} else if ( filter.field === 'type' ) {
			params.type = filter.value;
		}
	}

	return params;
};

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

	const view: View = useMemo< ViewTable >( () => {
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
			search: ( ( query.search as unknown[] )?.[ 0 ] as string ) ?? '',
			filters: buildFiltersFromQuery( query ),
			layout: persisted?.layout ?? defaultView.layout,
		};
		// navTick forces re-derive when the URL changes (popstate or our own
		// pushState writes) even though it isn't referenced in the body above.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ persisted, navTick ] );

	// Search input REST fan-out is kept in check by DataViews 4.15.4's
	// internal search-input debounce. If we ever pin a DataViews version that
	// removes that debounce, push every keystroke through a useDebounce here
	// before calling updateQueryString — and verify `useReportsFees` cancels
	// stale in-flight requests with an AbortController.
	const setView = useCallback(
		( next: View ) => {
			const filterParams = buildFilterQueryParams( next.filters ?? [] );
			const search = next.search ? [ next.search ] : undefined;

			updateQueryString(
				{
					orderby: next.sort?.field,
					order: next.sort?.direction,
					paged: next.page ? String( next.page ) : undefined,
					per_page: next.perPage ? String( next.perPage ) : undefined,
					search,
					...filterParams,
				},
				reportsPath
			);

			// `updateQueryString` only mutates history; nothing else in this
			// component subscribes to `pushstate`. Force the view memo to
			// re-read `getQuery()` on the next render so URL-only changes
			// (page, sort, search, filters) become visible.
			bumpNavTick();

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
		[ bumpNavTick, hasLoadedPersisted, persisted, updateUserPreferences ]
	);

	return [ view, setView ];
};
