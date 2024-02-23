/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export const ranges = {
	TODAY_VALUE: 'today',
	TODAY_LABEL: __( 'Today', 'woocommerce-payments' ),

	SEVEN_DAYS_VALUE: '7-days',
	SEVEN_DAYS_LABEL: __( 'Last 7 days', 'woocommerce-payments' ),

	FOUR_WEEKS_VALUE: '4-weeks',
	FOUR_WEEKS_LABEL: __( 'Last 4 weeks', 'woocommerce-payments' ),

	THREE_MONTHS_VALUE: '3-months',
	THREE_MONTHS_LABEL: __( 'Last 3 months', 'woocommerce-payments' ),

	TWELVE_MONTHS_VALUE: '12-months',
	TWELVE_MONTHS_LABEL: __( 'Last 12 months', 'woocommerce-payments' ),

	MONTH_TO_DATE_VALUE: 'month-to-date',
	MONTH_TO_DATE_LABEL: __( 'Month to date', 'woocommerce-payments' ),

	QUARTER_TO_DATE_VALUE: 'quarter-to-date',
	QUARTER_TO_DATE_LABEL: __( 'Quarter to date', 'woocommerce-payments' ),

	YEAR_TO_DATE_VALUE: 'year-to-date',
	YEAR_TO_DATE_LABEL: __( 'Year to date', 'woocommerce-payments' ),

	CUSTOM_VALUE: 'custom',
	CUSTOM_LABEL: __( 'Custom', 'woocommerce-payments' ),
};
