/** @format */

/**
 * External dependencies
 */
import { useCallback, useEffect, useMemo, useState } from 'react';
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
import type { ReportsPeriodRange } from '../period-selector';

const reportsPath = '/payments/reports';

const parseIntOr = ( value: unknown, fallback: number ): number => {
	const n = parseInt( String( value ?? '' ), 10 );
	return Number.isNaN( n ) ? fallback : n;
};

const buildFiltersFromQuery = (
	query: Record< string, unknown >
): Filter[] => {
	const filters: Filter[] = [];

	if ( query.date_between ) {
		filters.push( {
			field: 'date',
			operator: 'between' as Filter[ 'operator' ],
			value: query.date_between as string[],
		} );
	} else if ( query.date_before ) {
		filters.push( {
			field: 'date',
			operator: 'before' as Filter[ 'operator' ],
			value: query.date_before as string,
		} );
	} else if ( query.date_after ) {
		filters.push( {
			field: 'date',
			operator: 'after' as Filter[ 'operator' ],
			value: query.date_after as string,
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

const filtersToQueryDelta = (
	filters: Filter[]
): Record< string, unknown > => {
	const delta: Record< string, unknown > = {
		date_between: undefined,
		date_before: undefined,
		date_after: undefined,
		payment_method_type: undefined,
		type: undefined,
	};

	for ( const filter of filters ) {
		const op = filter.operator as string;
		if ( filter.field === 'date' ) {
			if ( op === 'between' ) {
				delta.date_between = filter.value;
			} else if ( op === 'before' ) {
				delta.date_before = filter.value;
			} else if ( op === 'after' ) {
				delta.date_after = filter.value;
			}
		} else if ( filter.field === 'payment_method' ) {
			delta.payment_method_type = filter.value;
		} else if ( filter.field === 'type' ) {
			delta.type = filter.value;
		}
	}

	return delta;
};

export const useFeesView = (
	period: ReportsPeriodRange
): [ View, ( next: View ) => void ] => {
	const { updateUserPreferences, ...userPrefs } = useUserPreferences();
	const persisted = (
		userPrefs as unknown as Record< string, PersistedFeesView >
	 )[ feesViewUserMetaKey ];

	void period; // period seeds default `date_between` via use-fees-data, not via the view object

	// Browser back/forward changes the URL without remounting; bump a tick on
	// popstate so the view re-derives from `getQuery()` instead of going stale.
	const [ navTick, setNavTick ] = useState( 0 );
	useEffect( () => {
		const onPopState = () => setNavTick( ( t ) => t + 1 );
		window.addEventListener( 'popstate', onPopState );
		return () => window.removeEventListener( 'popstate', onPopState );
	}, [] );

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
		// navTick forces re-derive when the URL changes via popstate even
		// though it isn't referenced in the body above.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ persisted, navTick ] );

	const setView = useCallback(
		( next: View ) => {
			const filterDelta = filtersToQueryDelta( next.filters ?? [] );
			const search = next.search ? [ next.search ] : undefined;

			updateQueryString(
				{
					orderby: next.sort?.field,
					order: next.sort?.direction,
					paged: next.page ? String( next.page ) : undefined,
					per_page: next.perPage ? String( next.perPage ) : undefined,
					search,
					...filterDelta,
				},
				reportsPath
			);

			const nextPersisted: PersistedFeesView = {
				fields: ( next.fields ?? [] ) as FeesFieldId[],
				perPage: next.perPage,
				layout: ( next as ViewTable ).layout,
			};

			if ( ! isPersistedShapeEqual( persisted, nextPersisted ) ) {
				updateUserPreferences( {
					[ feesViewUserMetaKey ]: nextPersisted,
				} );
			}
		},
		[ persisted, updateUserPreferences ]
	);

	return [ view, setView ];
};
