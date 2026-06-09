/** @format */

/**
 * External dependencies
 */
import React, { useMemo } from 'react';
import { DayPicker } from 'react-day-picker';
import type { DateRange } from 'react-day-picker';

/**
 * Internal dependencies
 */
import type { DateFilterValue, DateOperator } from './types';

const YMD = /^\d{4}-\d{2}-\d{2}$/;

// react-day-picker renders and emits Dates in the browser's LOCAL timezone.
// We keep YMD <-> Date conversions in the same local frame so that clicking
// "April 27" never round-trips into "April 26" (the previous behaviour, which
// used UTC noon and then read `getUTCDate()` on the value rdp returned in
// local time — a one-day shift for any westward timezone).
const ymdToDate = ( ymd: string ): Date | undefined => {
	if ( ! YMD.test( ymd ) ) {
		return undefined;
	}
	const [ y, m, d ] = ymd.split( '-' ).map( ( n ) => parseInt( n, 10 ) );
	return new Date( y, m - 1, d );
};

const dateToYmd = ( d: Date ): string => {
	const y = d.getFullYear();
	const m = String( d.getMonth() + 1 ).padStart( 2, '0' );
	const day = String( d.getDate() ).padStart( 2, '0' );
	return `${ y }-${ m }-${ day }`;
};

export interface CalendarProps {
	operator: DateOperator;
	value: DateFilterValue | undefined;
	onChange: ( next: DateFilterValue ) => void;
}

const buildSingle = (
	operator: DateOperator,
	ymd: string
): DateFilterValue => {
	if ( operator === 'on' ) {
		return { operator: 'on', value: ymd };
	}
	if ( operator === 'before' ) {
		return { operator: 'before', value: ymd };
	}
	if ( operator === 'after' ) {
		return { operator: 'after', value: ymd };
	}
	// Calendar should not call single in between mode, but for safety pin both
	// ends to the chosen date.
	return { operator: 'between', value: [ ymd, ymd ] };
};

export const Calendar: React.FC< CalendarProps > = ( {
	operator,
	value,
	onChange,
} ) => {
	const selectedSingle = useMemo< Date | undefined >( () => {
		if (
			value &&
			value.operator !== 'between' &&
			value.operator === operator
		) {
			return ymdToDate( value.value );
		}
		return undefined;
	}, [ operator, value ] );

	const selectedRange = useMemo< DateRange | undefined >( () => {
		if ( operator === 'between' && value?.operator === 'between' ) {
			const from = ymdToDate( value.value[ 0 ] );
			const to = ymdToDate( value.value[ 1 ] );
			if ( from && to ) {
				return { from, to };
			}
		}
		return undefined;
	}, [ operator, value ] );

	// Without an explicit `defaultMonth`, react-day-picker opens the
	// calendar on the current month — so a range that lives in April shows
	// up only as outside-days in the leading week of May, which reads as if
	// the wrong dates were highlighted. Anchor the visible month to the
	// active selection when there is one.
	const defaultMonth = useMemo< Date | undefined >( () => {
		if ( selectedRange?.from ) {
			return selectedRange.from;
		}
		if ( selectedSingle ) {
			return selectedSingle;
		}
		return undefined;
	}, [ selectedRange, selectedSingle ] );

	if ( operator === 'between' ) {
		return (
			<DayPicker
				mode="range"
				className="wcpay-date-filter__calendar"
				selected={ selectedRange }
				defaultMonth={ defaultMonth }
				onSelect={ ( range ) => {
					if ( ! range?.from ) {
						return;
					}
					// react-day-picker emits `{from, to: undefined}` on the
					// FIRST click of a new range after a complete one — so
					// committing only when both ends are present means the
					// start click silently disappears and the user thinks the
					// chip is "stuck" on the old start. Collapse the partial
					// selection to a same-day range; the next click extends.
					const from = dateToYmd( range.from );
					const to = range.to ? dateToYmd( range.to ) : from;
					onChange( {
						operator: 'between',
						value: [ from, to ],
					} );
				} }
				numberOfMonths={ 1 }
				showOutsideDays
			/>
		);
	}

	return (
		<DayPicker
			mode="single"
			className="wcpay-date-filter__calendar"
			selected={ selectedSingle }
			defaultMonth={ defaultMonth }
			onSelect={ ( date ) => {
				if ( date ) {
					onChange( buildSingle( operator, dateToYmd( date ) ) );
				}
			} }
			numberOfMonths={ 1 }
			showOutsideDays
		/>
	);
};
