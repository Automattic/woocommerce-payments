/** @format */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export type FeesDatePreset =
	| 'today'
	| 'yesterday'
	| 'last_7_days'
	| 'last_30_days'
	| 'this_month'
	| 'last_month'
	| 'this_year';

interface DatePresetElement {
	value: FeesDatePreset;
	label: string;
}

export const getFeesDatePresetElements = (): DatePresetElement[] => [
	{ value: 'today', label: __( 'Today', 'woocommerce-payments' ) },
	{ value: 'yesterday', label: __( 'Yesterday', 'woocommerce-payments' ) },
	{
		value: 'last_7_days',
		label: __( 'Last 7 days', 'woocommerce-payments' ),
	},
	{
		value: 'last_30_days',
		label: __( 'Last 30 days', 'woocommerce-payments' ),
	},
	{ value: 'this_month', label: __( 'This month', 'woocommerce-payments' ) },
	{ value: 'last_month', label: __( 'Last month', 'woocommerce-payments' ) },
	{ value: 'this_year', label: __( 'This year', 'woocommerce-payments' ) },
];

const presetValues: FeesDatePreset[] = [
	'today',
	'yesterday',
	'last_7_days',
	'last_30_days',
	'this_month',
	'last_month',
	'this_year',
];

export const isFeesDatePreset = ( value: unknown ): value is FeesDatePreset =>
	typeof value === 'string' &&
	presetValues.includes( value as FeesDatePreset );

const toYmd = ( d: Date ): string =>
	`${ d.getUTCFullYear() }-${ String( d.getUTCMonth() + 1 ).padStart(
		2,
		'0'
	) }-${ String( d.getUTCDate() ).padStart( 2, '0' ) }`;

/**
 * Resolve a preset key to a `[start, end]` date pair (YYYY-MM-DD, UTC) suitable
 * for the Fees REST endpoint's `date_between` parameter. Returns `undefined`
 * when the preset value is unrecognized.
 */
export const resolveDatePreset = (
	preset: unknown,
	now: Date = new Date()
): [ string, string ] | undefined => {
	if ( ! isFeesDatePreset( preset ) ) {
		return undefined;
	}

	const today = new Date(
		Date.UTC( now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate() )
	);

	switch ( preset ) {
		case 'today':
			return [ toYmd( today ), toYmd( today ) ];
		case 'yesterday': {
			const y = new Date( today );
			y.setUTCDate( y.getUTCDate() - 1 );
			return [ toYmd( y ), toYmd( y ) ];
		}
		case 'last_7_days': {
			const start = new Date( today );
			start.setUTCDate( start.getUTCDate() - 6 );
			return [ toYmd( start ), toYmd( today ) ];
		}
		case 'last_30_days': {
			const start = new Date( today );
			start.setUTCDate( start.getUTCDate() - 29 );
			return [ toYmd( start ), toYmd( today ) ];
		}
		case 'this_month': {
			const start = new Date(
				Date.UTC( today.getUTCFullYear(), today.getUTCMonth(), 1 )
			);
			return [ toYmd( start ), toYmd( today ) ];
		}
		case 'last_month': {
			const start = new Date(
				Date.UTC( today.getUTCFullYear(), today.getUTCMonth() - 1, 1 )
			);
			const end = new Date(
				Date.UTC( today.getUTCFullYear(), today.getUTCMonth(), 0 )
			);
			return [ toYmd( start ), toYmd( end ) ];
		}
		case 'this_year': {
			const start = new Date( Date.UTC( today.getUTCFullYear(), 0, 1 ) );
			return [ toYmd( start ), toYmd( today ) ];
		}
	}
};
