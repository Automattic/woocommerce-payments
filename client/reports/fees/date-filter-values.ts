/** @format */

/**
 * External dependencies
 */
import type { Filter } from '@wordpress/dataviews/wp';

/**
 * Internal dependencies
 */
import type { DateFilterValue } from 'wcpay/reports/date-filter';
import {
	parseDateFilterFromQuery,
	serializeDateFilterToQuery,
} from 'wcpay/reports/date-filter/url';

const millisecondsPerDay = 86400000;

const isYmd = ( value: string | undefined ): value is string =>
	/^\d{4}-\d{2}-\d{2}$/.test( value ?? '' );

export interface FeesDateQueryParams {
	date_between?: string[];
	date_before?: string;
	date_after?: string;
}

export const getFeesDateFilterValue = (
	filter: Pick< Filter, 'operator' | 'value' > | undefined
): DateFilterValue | undefined => {
	if ( ! filter ) {
		return undefined;
	}

	if ( filter.operator === 'between' ) {
		const value = filter.value;
		if (
			Array.isArray( value ) &&
			isYmd( value[ 0 ] as string | undefined ) &&
			isYmd( value[ 1 ] as string | undefined )
		) {
			return {
				operator: 'between',
				value: [ value[ 0 ] as string, value[ 1 ] as string ],
			};
		}
		return undefined;
	}

	if (
		( filter.operator === 'on' ||
			filter.operator === 'before' ||
			filter.operator === 'after' ) &&
		isYmd( filter.value as string | undefined )
	) {
		return {
			operator: filter.operator,
			value: filter.value as string,
		};
	}

	return undefined;
};

export const buildFeesDateQueryFromFilter = (
	filter: Pick< Filter, 'operator' | 'value' > | undefined
): FeesDateQueryParams => {
	const dateFilter = getFeesDateFilterValue( filter );

	if ( ! dateFilter ) {
		return {};
	}

	if ( dateFilter.operator === 'on' ) {
		return {
			date_between: [ dateFilter.value, dateFilter.value ],
		};
	}

	if ( dateFilter.operator === 'between' ) {
		return {
			date_between: dateFilter.value,
		};
	}

	if ( dateFilter.operator === 'before' ) {
		return {
			date_before: dateFilter.value,
		};
	}

	return {
		date_after: dateFilter.value,
	};
};

export const parseFeesDateFilterFromQuery = (
	query: Record< string, unknown >
): Filter | undefined => {
	const value = parseDateFilterFromQuery( query );
	if ( ! value ) {
		return undefined;
	}

	return {
		field: 'date',
		operator: value.operator,
		value: value.value,
	};
};

export const buildFeesDateQueryFromUrlQuery = (
	query: Record< string, unknown >
): FeesDateQueryParams => {
	return buildFeesDateQueryFromFilter(
		parseFeesDateFilterFromQuery( query )
	);
};

export const serializeFeesDateFilterToQuery = (
	filter: Pick< Filter, 'operator' | 'value' > | undefined
): Record< string, unknown > => {
	return {
		date_preset: undefined,
		...serializeDateFilterToQuery( getFeesDateFilterValue( filter ) ),
	};
};

export const getFeesDateFilterRangeDays = (
	filter: Pick< Filter, 'operator' | 'value' > | undefined
): number | null => {
	const dateFilter = getFeesDateFilterValue( filter );
	if ( ! dateFilter || dateFilter.operator !== 'between' ) {
		return null;
	}

	const start = new Date( dateFilter.value[ 0 ] ).getTime();
	const end = new Date( dateFilter.value[ 1 ] ).getTime();
	return Math.round( ( end - start ) / millisecondsPerDay );
};
