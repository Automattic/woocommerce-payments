/** @format */

/**
 * Internal dependencies
 */
import type { DateFilterValue } from './types';

const YMD = /^\d{4}-\d{2}-\d{2}$/;

const isYmd = ( value: unknown ): value is string =>
	typeof value === 'string' && YMD.test( value );

export interface DateFilterQueryKeys {
	date_between?: [ string, string ];
	date_before?: string;
	date_after?: string;
}

/**
 * Parse the URL query into a DateFilterValue, recognising the existing
 * `date_between` / `date_before` / `date_after` keys. `date_between=[X,X]`
 * collapses to an `on` value (same-day range). Returns `undefined` when no
 * date filter is active in the URL.
 */
export const parseDateFilterFromQuery = (
	query: Record< string, unknown >
): DateFilterValue | undefined => {
	const between = query.date_between;
	if ( Array.isArray( between ) && between.length === 2 ) {
		const [ start, end ] = between;
		if ( isYmd( start ) && isYmd( end ) ) {
			if ( start === end ) {
				return { operator: 'on', value: start };
			}
			return { operator: 'between', value: [ start, end ] };
		}
	}

	const before = query.date_before;
	if ( isYmd( before ) ) {
		return { operator: 'before', value: before };
	}

	const after = query.date_after;
	if ( isYmd( after ) ) {
		return { operator: 'after', value: after };
	}

	return undefined;
};

/**
 * Translate a DateFilterValue (or its absence) into a query patch that always
 * sets every owned key — including the ones we want to clear — to `undefined`,
 * so `updateQueryString` strips stale keys.
 */
export const serializeDateFilterToQuery = (
	value: DateFilterValue | undefined
): Record< string, unknown > => {
	const params: Record< string, unknown > = {
		date_between: undefined,
		date_before: undefined,
		date_after: undefined,
	};

	if ( ! value ) {
		return params;
	}

	switch ( value.operator ) {
		case 'on':
			params.date_between = [ value.value, value.value ];
			break;
		case 'between':
			params.date_between = value.value;
			break;
		case 'before':
			params.date_before = value.value;
			break;
		case 'after':
			params.date_after = value.value;
			break;
	}

	return params;
};
