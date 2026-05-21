/** @format */

/**
 * External dependencies
 */
import { useCallback, useEffect, useState } from 'react';
import { getQuery, updateQueryString } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import {
	parseDateFilterFromQuery,
	serializeDateFilterToQuery,
	type DateFilterValue,
} from 'wcpay/reports/date-filter';
import {
	getLastFullCalendarMonthUTC,
	type ReportsPeriodRange,
} from 'wcpay/reports/period-selector';

const reportsPath = '/payments/reports';

interface UseBalanceDateFilterResult {
	value: DateFilterValue | undefined;
	period: ReportsPeriodRange;
	setValue: ( next: DateFilterValue | undefined ) => void;
}

const parseYmd = ( ymd: string ): [ number, number, number ] => {
	const [ year, month, day ] = ymd.split( '-' ).map( Number );
	return [ year, month, day ];
};

const toStartOfDayUTC = ( ymd: string ): string => {
	const [ year, month, day ] = parseYmd( ymd );
	return new Date(
		Date.UTC( year, month - 1, day, 0, 0, 0, 0 )
	).toISOString();
};

const toEndOfDayUTC = ( ymd: string ): string => {
	const [ year, month, day ] = parseYmd( ymd );
	return new Date(
		Date.UTC( year, month - 1, day, 23, 59, 59, 999 )
	).toISOString();
};

const toYmdUTC = ( date: Date ): string =>
	[
		date.getUTCFullYear(),
		String( date.getUTCMonth() + 1 ).padStart( 2, '0' ),
		String( date.getUTCDate() ).padStart( 2, '0' ),
	].join( '-' );

const getMonthStartYmd = ( ymd: string ): string => {
	const [ year, month ] = parseYmd( ymd );
	return toYmdUTC( new Date( Date.UTC( year, month - 1, 1 ) ) );
};

const getMonthEndYmd = ( ymd: string ): string => {
	const [ year, month ] = parseYmd( ymd );
	return toYmdUTC( new Date( Date.UTC( year, month, 0 ) ) );
};

const getLatestCompleteDayYmd = ( now: Date ): string =>
	toYmdUTC(
		new Date(
			Date.UTC(
				now.getUTCFullYear(),
				now.getUTCMonth(),
				now.getUTCDate() - 1
			)
		)
	);

const minYmd = ( first: string, second: string ): string =>
	first <= second ? first : second;

export const getPeriodForDateFilter = (
	value: DateFilterValue | undefined,
	now: Date = new Date()
): ReportsPeriodRange => {
	if ( ! value ) {
		return getLastFullCalendarMonthUTC( now );
	}

	if ( value.operator === 'on' ) {
		return {
			start: toStartOfDayUTC( value.value ),
			end: toEndOfDayUTC( value.value ),
		};
	}

	if ( value.operator === 'between' ) {
		return {
			start: toStartOfDayUTC( value.value[ 0 ] ),
			end: toEndOfDayUTC( value.value[ 1 ] ),
		};
	}

	if ( value.operator === 'before' ) {
		return {
			start: toStartOfDayUTC( getMonthStartYmd( value.value ) ),
			end: toEndOfDayUTC( value.value ),
		};
	}

	const endYmd = minYmd(
		getMonthEndYmd( value.value ),
		getLatestCompleteDayYmd( now )
	);
	return {
		start: toStartOfDayUTC( value.value ),
		end: toEndOfDayUTC( endYmd ),
	};
};

export const useBalanceDateFilter = (
	now: Date = new Date()
): UseBalanceDateFilterResult => {
	const [ navTick, setNavTick ] = useState( 0 );
	const bumpNavTick = useCallback(
		() => setNavTick( ( tick ) => tick + 1 ),
		[]
	);

	useEffect( () => {
		window.addEventListener( 'popstate', bumpNavTick );
		return () => window.removeEventListener( 'popstate', bumpNavTick );
	}, [ bumpNavTick ] );

	// `navTick` deliberately forces this render to read the current URL again
	// after browser navigation or after this hook writes a new query string.
	void navTick;
	const value = parseDateFilterFromQuery(
		getQuery() as Record< string, unknown >
	);
	const period = getPeriodForDateFilter( value, now );

	const setValue = useCallback( ( next: DateFilterValue | undefined ) => {
		updateQueryString( serializeDateFilterToQuery( next ), reportsPath );
		setNavTick( ( tick ) => tick + 1 );
	}, [] );

	return {
		value,
		period,
		setValue,
	};
};
