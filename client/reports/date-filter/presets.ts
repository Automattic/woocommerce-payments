/** @format */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type {
	DateFilterPresetElement,
	DateFilterValue,
	DateOperator,
} from './types';

export type SingleDatePreset =
	| 'today'
	| 'yesterday'
	| 'past_week'
	| 'past_month';

export type RangePreset =
	| 'last_month'
	| 'month_to_date'
	| 'last_year'
	| 'year_to_date';

export type CustomPreset = 'custom';

export type DatePreset = SingleDatePreset | RangePreset | CustomPreset;

const toYmd = ( d: Date ): string =>
	`${ d.getFullYear() }-${ String( d.getMonth() + 1 ).padStart(
		2,
		'0'
	) }-${ String( d.getDate() ).padStart( 2, '0' ) }`;

const startOfDay = ( now: Date ): Date =>
	new Date( now.getFullYear(), now.getMonth(), now.getDate() );

const singleDatePresetLabels = (): Record< SingleDatePreset, string > => ( {
	today: __( 'Today', 'woocommerce-payments' ),
	yesterday: __( 'Yesterday', 'woocommerce-payments' ),
	past_week: __( 'Past week', 'woocommerce-payments' ),
	past_month: __( 'Past month', 'woocommerce-payments' ),
} );

const rangePresetLabels = (): Record< RangePreset, string > => ( {
	last_month: __( 'Last month', 'woocommerce-payments' ),
	month_to_date: __( 'Month to date', 'woocommerce-payments' ),
	last_year: __( 'Last year', 'woocommerce-payments' ),
	year_to_date: __( 'Year to date', 'woocommerce-payments' ),
} );

/**
 * Return the preset chip list for the given operator. `between` operates on
 * date ranges; `on`/`before`/`after` operate on a single anchor date.
 */
export const getPresetsForOperator = (
	operator: DateOperator
): DateFilterPresetElement[] => {
	const customLabel = __( 'Custom', 'woocommerce-payments' );

	if ( operator === 'between' ) {
		const labels = rangePresetLabels();
		return [
			{ value: 'last_month', label: labels.last_month },
			{ value: 'month_to_date', label: labels.month_to_date },
			{ value: 'last_year', label: labels.last_year },
			{ value: 'year_to_date', label: labels.year_to_date },
			{ value: 'custom', label: customLabel },
		];
	}

	const labels = singleDatePresetLabels();
	return [
		{ value: 'today', label: labels.today },
		{ value: 'yesterday', label: labels.yesterday },
		{ value: 'past_week', label: labels.past_week },
		{ value: 'past_month', label: labels.past_month },
		{ value: 'custom', label: customLabel },
	];
};

const resolveSingleAnchorDate = (
	preset: SingleDatePreset,
	now: Date
): string => {
	const today = startOfDay( now );

	switch ( preset ) {
		case 'today':
			return toYmd( today );
		case 'yesterday': {
			const d = new Date( today );
			d.setDate( d.getDate() - 1 );
			return toYmd( d );
		}
		case 'past_week': {
			const d = new Date( today );
			d.setDate( d.getDate() - 7 );
			return toYmd( d );
		}
		case 'past_month': {
			const d = new Date( today );
			d.setMonth( d.getMonth() - 1 );
			return toYmd( d );
		}
	}
};

const resolveRange = ( preset: RangePreset, now: Date ): [ string, string ] => {
	const today = startOfDay( now );
	const year = today.getFullYear();
	const month = today.getMonth();

	switch ( preset ) {
		case 'last_month': {
			const start = new Date( year, month - 1, 1 );
			const end = new Date( year, month, 0 );
			return [ toYmd( start ), toYmd( end ) ];
		}
		case 'month_to_date': {
			const start = new Date( year, month, 1 );
			return [ toYmd( start ), toYmd( today ) ];
		}
		case 'last_year': {
			const start = new Date( year - 1, 0, 1 );
			const end = new Date( year - 1, 11, 31 );
			return [ toYmd( start ), toYmd( end ) ];
		}
		case 'year_to_date': {
			const start = new Date( year, 0, 1 );
			return [ toYmd( start ), toYmd( today ) ];
		}
	}
};

const singleDatePresets: SingleDatePreset[] = [
	'today',
	'yesterday',
	'past_week',
	'past_month',
];

const rangePresets: RangePreset[] = [
	'last_month',
	'month_to_date',
	'last_year',
	'year_to_date',
];

const isSingleDatePreset = ( value: unknown ): value is SingleDatePreset =>
	typeof value === 'string' &&
	singleDatePresets.includes( value as SingleDatePreset );

const isRangePreset = ( value: unknown ): value is RangePreset =>
	typeof value === 'string' && rangePresets.includes( value as RangePreset );

/**
 * Resolve a preset key + operator into a `DateFilterValue`. Returns `undefined`
 * for unrecognized presets, the `custom` sentinel, or operator/preset
 * mismatches (e.g., a range preset against a single-date operator).
 */
export const resolvePreset = (
	preset: string,
	operator: DateOperator,
	now: Date = new Date()
): DateFilterValue | undefined => {
	if ( preset === 'custom' ) {
		return undefined;
	}

	if ( operator === 'between' ) {
		if ( ! isRangePreset( preset ) ) {
			return undefined;
		}
		return { operator: 'between', value: resolveRange( preset, now ) };
	}

	if ( ! isSingleDatePreset( preset ) ) {
		return undefined;
	}
	const anchor = resolveSingleAnchorDate( preset, now );
	if ( operator === 'on' ) {
		return { operator: 'on', value: anchor };
	}
	if ( operator === 'before' ) {
		return { operator: 'before', value: anchor };
	}
	return { operator: 'after', value: anchor };
};

/**
 * Try to recognize a `DateFilterValue` as one of the named presets, given a
 * reference "now". Returns the preset key, or `'custom'` if no preset matches.
 * Useful so the popover can highlight the active preset chip on reopen.
 */
export const matchPreset = (
	value: DateFilterValue,
	now: Date = new Date()
): DatePreset => {
	if ( value.operator === 'between' ) {
		for ( const preset of rangePresets ) {
			const range = resolveRange( preset, now );
			if (
				range[ 0 ] === value.value[ 0 ] &&
				range[ 1 ] === value.value[ 1 ]
			) {
				return preset;
			}
		}
		return 'custom';
	}

	for ( const preset of singleDatePresets ) {
		if ( resolveSingleAnchorDate( preset, now ) === value.value ) {
			return preset;
		}
	}
	return 'custom';
};
