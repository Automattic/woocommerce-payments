/** @format */

/**
 * External dependencies
 */
import { useCallback, useEffect, useMemo, useState } from 'react';
import { getQuery, updateQueryString } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import {
	parseDateFilterFromQuery,
	serializeDateFilterToQuery,
	type DateFilterValue,
} from 'wcpay/reports/date-filter';

const reportsPath = '/payments/reports';

/**
 * Hook that owns the Fees report's date filter, bidirectionally synced with
 * the URL via `date_between` / `date_before` / `date_after`. Returns the
 * current value and a setter; the setter accepts `undefined` to clear.
 *
 * Kept independent of `useFeesView` so the date chip can render inline with
 * the DataViews chip row without poking at DataViews internals or fighting
 * DataViews' operator vocabulary.
 */
export const useFeesDateFilter = (): [
	DateFilterValue | undefined,
	( next: DateFilterValue | undefined ) => void
] => {
	const [ navTick, setNavTick ] = useState( 0 );
	useEffect( () => {
		const bump = () => setNavTick( ( t ) => t + 1 );
		window.addEventListener( 'popstate', bump );
		return () => window.removeEventListener( 'popstate', bump );
	}, [] );

	const derived = useMemo< DateFilterValue | undefined >( () => {
		return parseDateFilterFromQuery(
			getQuery() as Record< string, unknown >
		);
		// navTick forces re-derive when the URL changes via popstate. The
		// internal setter also bumps the URL — but our own writes already
		// flow through React state via `setValue` below, so this memo only
		// needs to listen for external (popstate / programmatic) changes.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ navTick ] );

	const [ value, setValue ] = useState< DateFilterValue | undefined >(
		derived
	);

	// Re-seed local state when an external navigation event changes the URL.
	useEffect( () => {
		setValue( derived );
	}, [ derived ] );

	const set = useCallback( ( next: DateFilterValue | undefined ) => {
		setValue( next );
		updateQueryString( serializeDateFilterToQuery( next ), reportsPath );
	}, [] );

	return [ value, set ];
};
