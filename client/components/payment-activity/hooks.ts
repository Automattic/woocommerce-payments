/**
 * External dependencies
 */
import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import moment from 'moment';

interface DateRange {
	date_start: string;
	date_end: string;
	key: string;
}

export const usePaymentActivityDateRangePresets = (): {
	selectedDateRange: DateRange;
	setSelectedDateRange: ( dateRange: DateRange ) => void;
	dateRangePresets: {
		[ key: string ]: {
			start: moment.Moment;
			end: moment.Moment;
			displayKey: string;
		};
	};
} => {
	const now = moment();
	const yesterdayEndOfDay = moment()
		.clone()
		.subtract( 1, 'd' )
		.set( { hour: 23, minute: 59, second: 59, millisecond: 0 } );
	const todayEndOfDay = moment()
		.clone()
		.set( { hour: 23, minute: 59, second: 59, millisecond: 0 } );

	const dateRangePresets: {
		[ key: string ]: {
			start: moment.Moment;
			end: moment.Moment;
			displayKey: string;
		};
	} = {
		today: {
			start: now
				.clone()
				.set( { hour: 0, minute: 0, second: 0, millisecond: 0 } ),
			end: todayEndOfDay,
			displayKey: __( 'Today', 'woocommerce-payments' ),
		},
		last_7_days: {
			start: now
				.clone()
				.subtract( 7, 'days' )
				.set( { hour: 0, minute: 0, second: 0, millisecond: 0 } ),
			end: yesterdayEndOfDay,
			displayKey: __( 'Last 7 days', 'woocommerce-payments' ),
		},
		last_4_weeks: {
			start: now
				.clone()
				.subtract( 4, 'weeks' )
				.set( { hour: 0, minute: 0, second: 0, millisecond: 0 } ),
			end: yesterdayEndOfDay,
			displayKey: __( 'Last 4 weeks', 'woocommerce-payments' ),
		},
		last_3_months: {
			start: now
				.clone()
				.subtract( 3, 'months' )
				.set( { hour: 0, minute: 0, second: 0, millisecond: 0 } ),
			end: yesterdayEndOfDay,
			displayKey: __( 'Last 3 months', 'woocommerce-payments' ),
		},
		last_12_months: {
			start: now
				.clone()
				.subtract( 12, 'months' )
				.set( { hour: 0, minute: 0, second: 0, millisecond: 0 } ),
			end: yesterdayEndOfDay,
			displayKey: __( 'Last 12 months', 'woocommerce-payments' ),
		},
		month_to_date: {
			start: now.clone().startOf( 'month' ),
			end: todayEndOfDay,
			displayKey: __( 'Month to date', 'woocommerce-payments' ),
		},
		quarter_to_date: {
			start: now.clone().startOf( 'quarter' ),
			end: todayEndOfDay,
			displayKey: __( 'Quarter to date', 'woocommerce-payments' ),
		},
		year_to_date: {
			start: now.clone().startOf( 'year' ),
			end: todayEndOfDay,
			displayKey: __( 'Year to date', 'woocommerce-payments' ),
		},
		all_time: {
			start: moment(
				wcpaySettings.accountStatus.created,
				'YYYY-MM-DD\\THH:mm:ss'
			),
			end: todayEndOfDay,
			displayKey: __( 'All time', 'woocommerce-payments' ),
		},
	};

	const defaultDateRange = {
		key: 'last_7_days',
		date_start: dateRangePresets.last_7_days.start.format(
			'YYYY-MM-DD\\THH:mm:ss'
		),
		date_end: dateRangePresets.last_7_days.end.format(
			'YYYY-MM-DD\\THH:mm:ss'
		),
	};

	const [ selectedDateRange, setSelectedDateRange ] = useState( {
		date_start: defaultDateRange.date_start,
		date_end: defaultDateRange.date_end,
		key: defaultDateRange.key,
	} );

	return {
		selectedDateRange,
		setSelectedDateRange,
		dateRangePresets,
	};
};
