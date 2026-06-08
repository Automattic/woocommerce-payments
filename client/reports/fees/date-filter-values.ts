/** @format */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import type { Option } from '@wordpress/dataviews/wp';

/**
 * Internal dependencies
 */
import { formatDateFilterChipLabel } from 'wcpay/reports/date-filter/formatters';
import type { DateFilterValue } from 'wcpay/reports/date-filter';
import { resolvePreset } from 'wcpay/reports/date-filter/presets';

const customDatePrefix = 'custom:';

const feesDatePresets = [
	'last_month',
	'month_to_date',
	'year_to_date',
] as const;

type FeesDatePreset = ( typeof feesDatePresets )[ number ];

const isFeesDatePreset = ( value: unknown ): value is FeesDatePreset =>
	typeof value === 'string' &&
	feesDatePresets.includes( value as FeesDatePreset );

const getFeesDatePresetElements = (): Option< string >[] => [
	{ value: 'last_month', label: __( 'Last month', 'woocommerce-payments' ) },
	{
		value: 'month_to_date',
		label: __( 'Month to date', 'woocommerce-payments' ),
	},
	{
		value: 'year_to_date',
		label: __( 'Year to date', 'woocommerce-payments' ),
	},
];

const isYmd = ( value: string | undefined ): value is string =>
	/^\d{4}-\d{2}-\d{2}$/.test( value ?? '' );

export const encodeCustomDateFilterValue = (
	value: DateFilterValue
): string => {
	if ( value.operator === 'between' ) {
		return `${ customDatePrefix }between:${ value.value[ 0 ] }:${ value.value[ 1 ] }`;
	}
	return `${ customDatePrefix }${ value.operator }:${ value.value }`;
};

const decodeCustomDateFilterValue = (
	value: unknown
): DateFilterValue | undefined => {
	if ( typeof value !== 'string' || ! value.startsWith( customDatePrefix ) ) {
		return undefined;
	}

	const [ operator, first, second ] = value
		.slice( customDatePrefix.length )
		.split( ':' );

	if ( operator === 'between' && isYmd( first ) && isYmd( second ) ) {
		return {
			operator: 'between',
			value: [ first, second ],
		};
	}

	if (
		( operator === 'on' ||
			operator === 'before' ||
			operator === 'after' ) &&
		isYmd( first )
	) {
		return {
			operator,
			value: first,
		};
	}

	return undefined;
};

export const resolveFeesDateFilterValue = (
	value: unknown,
	now: Date = new Date()
): DateFilterValue | undefined => {
	if ( isFeesDatePreset( value ) ) {
		return resolvePreset( value, 'between', now );
	}
	return decodeCustomDateFilterValue( value );
};

export interface FeesDateQueryParams {
	date_between?: string[];
	date_before?: string;
	date_after?: string;
}

export const buildFeesDateQueryFromFilterValue = (
	value: unknown,
	now: Date = new Date()
): FeesDateQueryParams => {
	const dateFilter = resolveFeesDateFilterValue( value, now );

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

export const parseFeesDateFilterValueFromQuery = (
	query: Record< string, unknown >
): string | undefined => {
	if ( isFeesDatePreset( query.date_preset ) ) {
		return query.date_preset;
	}

	const between = query.date_between;
	if (
		Array.isArray( between ) &&
		isYmd( between[ 0 ] as string | undefined ) &&
		isYmd( between[ 1 ] as string | undefined )
	) {
		const value: DateFilterValue =
			between[ 0 ] === between[ 1 ]
				? { operator: 'on', value: between[ 0 ] as string }
				: {
						operator: 'between',
						value: [
							between[ 0 ] as string,
							between[ 1 ] as string,
						],
				  };
		return encodeCustomDateFilterValue( value );
	}

	if ( isYmd( query.date_before as string | undefined ) ) {
		return encodeCustomDateFilterValue( {
			operator: 'before',
			value: query.date_before as string,
		} );
	}

	if ( isYmd( query.date_after as string | undefined ) ) {
		return encodeCustomDateFilterValue( {
			operator: 'after',
			value: query.date_after as string,
		} );
	}

	return undefined;
};

export const buildFeesDateQueryFromUrlQuery = (
	query: Record< string, unknown >,
	now: Date = new Date()
): FeesDateQueryParams => {
	const normalizedQuery = buildFeesDateQueryFromFilterValue(
		parseFeesDateFilterValueFromQuery( query ),
		now
	);

	if (
		normalizedQuery.date_between ||
		normalizedQuery.date_before ||
		normalizedQuery.date_after
	) {
		return normalizedQuery;
	}

	return {
		date_between:
			Array.isArray( query.date_between ) &&
			query.date_between.every(
				( value ) => typeof value === 'string' && value !== ''
			)
				? ( query.date_between as string[] )
				: undefined,
		date_before:
			typeof query.date_before === 'string' && query.date_before !== ''
				? query.date_before
				: undefined,
		date_after:
			typeof query.date_after === 'string' && query.date_after !== ''
				? query.date_after
				: undefined,
	};
};

export const serializeFeesDateFilterValueToQuery = (
	value: unknown
): Record< string, unknown > => {
	const params: Record< string, unknown > = {
		date_preset: undefined,
		date_between: undefined,
		date_before: undefined,
		date_after: undefined,
	};

	if ( isFeesDatePreset( value ) ) {
		params.date_preset = value;
		return params;
	}

	const customValue = decodeCustomDateFilterValue( value );
	if ( ! customValue ) {
		return params;
	}

	if ( customValue.operator === 'on' ) {
		params.date_between = [ customValue.value, customValue.value ];
	} else if ( customValue.operator === 'between' ) {
		params.date_between = customValue.value;
	} else if ( customValue.operator === 'before' ) {
		params.date_before = customValue.value;
	} else if ( customValue.operator === 'after' ) {
		params.date_after = customValue.value;
	}

	return params;
};

export const buildFeesDateFilterElements = (
	activeValue: unknown
): Option< string >[] => {
	const elements = [ ...getFeesDatePresetElements() ];

	const customValue = decodeCustomDateFilterValue( activeValue );
	if ( customValue && typeof activeValue === 'string' ) {
		elements.push( {
			value: activeValue,
			label: formatDateFilterChipLabel( customValue ),
		} );
	}

	return elements;
};
