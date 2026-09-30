/** @format */

/**
 * External dependencies
 */
import { useCallback, useContext, useEffect, useRef, useState } from 'react';
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
import { BalanceDateFilterNowContext } from './context';

export { BalanceDateFilterNowContext };

const reportsPath = '/payments/reports';
const balanceDateFilterChangeEvent = 'wcpay-balance-date-filter-change';

interface UseBalanceDateFilterResult {
	value: DateFilterValue | undefined;
	period: ReportsPeriodRange;
	hasDateFilterValue: boolean;
	setValue: ( next: DateFilterValue | undefined ) => void;
}

interface LocalDateFilterValue {
	value: DateFilterValue | undefined;
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

const capYmdAtLatestCompleteDay = ( ymd: string, now: Date ): string =>
	minYmd( ymd, getLatestCompleteDayYmd( now ) );

const sortYmdRange = ( start: string, end: string ): [ string, string ] =>
	start <= end ? [ start, end ] : [ end, start ];

const normalizeDateFilterValue = (
	value: DateFilterValue | undefined,
	now: Date
): DateFilterValue | undefined => {
	if ( ! value ) {
		return undefined;
	}

	if ( value.operator === 'between' ) {
		const [ start, end ] = sortYmdRange(
			capYmdAtLatestCompleteDay( value.value[ 0 ], now ),
			capYmdAtLatestCompleteDay( value.value[ 1 ], now )
		);

		return {
			operator: 'between',
			value: [ start, end ],
		};
	}

	if ( value.operator === 'on' ) {
		return {
			operator: 'on',
			value: capYmdAtLatestCompleteDay( value.value, now ),
		};
	}

	if ( value.operator === 'before' ) {
		return {
			operator: 'before',
			value: capYmdAtLatestCompleteDay( value.value, now ),
		};
	}

	return {
		operator: 'after',
		value: capYmdAtLatestCompleteDay( value.value, now ),
	};
};

const getLastFullCalendarMonthDateFilter = ( now: Date ): DateFilterValue => {
	const range = getLastFullCalendarMonthUTC( now );

	return {
		operator: 'between',
		value: [ range.start.slice( 0, 10 ), range.end.slice( 0, 10 ) ],
	};
};

export const getPeriodForDateFilter = (
	value: DateFilterValue | undefined,
	now: Date = new Date()
): ReportsPeriodRange => {
	const normalizedValue = normalizeDateFilterValue( value, now );
	if ( ! normalizedValue ) {
		return getLastFullCalendarMonthUTC( now );
	}

	if ( normalizedValue.operator === 'on' ) {
		return {
			start: toStartOfDayUTC( normalizedValue.value ),
			end: toEndOfDayUTC( normalizedValue.value ),
		};
	}

	if ( normalizedValue.operator === 'between' ) {
		return {
			start: toStartOfDayUTC( normalizedValue.value[ 0 ] ),
			end: toEndOfDayUTC( normalizedValue.value[ 1 ] ),
		};
	}

	if ( normalizedValue.operator === 'before' ) {
		return {
			start: toStartOfDayUTC( getMonthStartYmd( normalizedValue.value ) ),
			end: toEndOfDayUTC( normalizedValue.value ),
		};
	}

	const endYmd = minYmd(
		getMonthEndYmd( normalizedValue.value ),
		getLatestCompleteDayYmd( now )
	);
	return {
		start: toStartOfDayUTC( normalizedValue.value ),
		end: toEndOfDayUTC( endYmd ),
	};
};

export const useBalanceDateFilter = (
	now?: Date
): UseBalanceDateFilterResult => {
	const contextNow = useContext( BalanceDateFilterNowContext );
	const stableNow = useRef( now ?? contextNow ?? new Date() ).current;
	const isUpdatingUrlRef = useRef( false );
	const [ navTick, setNavTick ] = useState( 0 );
	const [ localValue, setLocalValue ] = useState<
		LocalDateFilterValue | undefined
	>();
	const bumpNavTick = useCallback(
		() => setNavTick( ( tick ) => tick + 1 ),
		[]
	);
	const readUrlValue = useCallback(
		() =>
			normalizeDateFilterValue(
				parseDateFilterFromQuery(
					getQuery() as Record< string, unknown >
				),
				stableNow
			),
		[ stableNow ]
	);
	const handleLocalDateFilterChange = useCallback(
		( event: Event ) => {
			setLocalValue(
				( event as CustomEvent< LocalDateFilterValue > ).detail
			);
			bumpNavTick();
		},
		[ bumpNavTick ]
	);
	const handleHistoryChange = useCallback( () => {
		if ( ! isUpdatingUrlRef.current ) {
			setLocalValue( undefined );
		}
		bumpNavTick();
	}, [ bumpNavTick ] );

	useEffect( () => {
		window.addEventListener(
			balanceDateFilterChangeEvent,
			handleLocalDateFilterChange
		);
		window.addEventListener( 'popstate', handleHistoryChange );
		window.addEventListener( 'pushstate', handleHistoryChange );
		window.addEventListener( 'replacestate', handleHistoryChange );
		return () => {
			window.removeEventListener(
				balanceDateFilterChangeEvent,
				handleLocalDateFilterChange
			);
			window.removeEventListener( 'popstate', handleHistoryChange );
			window.removeEventListener( 'pushstate', handleHistoryChange );
			window.removeEventListener( 'replacestate', handleHistoryChange );
		};
	}, [ handleHistoryChange, handleLocalDateFilterChange ] );

	// `navTick` deliberately forces this render to read the current URL again
	// after browser navigation or after this hook writes a new query string.
	void navTick;
	// Keep local writes authoritative during an open interaction. The URL parser
	// collapses same-day `date_between` values to `on`, which would interrupt
	// in-progress range selection after the first calendar click.
	const value = localValue
		? localValue.value
		: readUrlValue() ?? getLastFullCalendarMonthDateFilter( stableNow );
	const period = getPeriodForDateFilter( value, stableNow );
	const hasDateFilterValue = value !== undefined;

	const setValue = useCallback(
		( next: DateFilterValue | undefined ) => {
			const normalizedValue = normalizeDateFilterValue( next, stableNow );
			isUpdatingUrlRef.current = true;
			try {
				updateQueryString(
					serializeDateFilterToQuery( normalizedValue ),
					reportsPath
				);
			} finally {
				isUpdatingUrlRef.current = false;
			}
			window.dispatchEvent(
				new CustomEvent< LocalDateFilterValue >(
					balanceDateFilterChangeEvent,
					{ detail: { value: normalizedValue } }
				)
			);
			setNavTick( ( tick ) => tick + 1 );
		},
		[ stableNow ]
	);

	return {
		value,
		period,
		hasDateFilterValue,
		setValue,
	};
};
