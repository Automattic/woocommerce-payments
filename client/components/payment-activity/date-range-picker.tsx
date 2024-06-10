/**
 * External dependencies
 */
import React, { useState } from 'react';
import moment from 'moment';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import InlineLabelSelect from '../inline-label-select';
import { DateRange } from './types';

const now = moment();
const yesterdayEndOfDay = moment()
	.clone()
	.subtract( 1, 'd' )
	.set( { hour: 23, minute: 59, second: 59, millisecond: 0 } );
const todayEndOfDay = moment()
	.clone()
	.set( { hour: 23, minute: 59, second: 59, millisecond: 0 } );
const timeOptions: {
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

const formatDateRange = (
	start: moment.Moment,
	end: moment.Moment
): string => {
	// Today - show only today's date.
	if ( start.isSame( end, 'day' ) ) {
		return start.format( 'MMMM D, YYYY' );
	}

	// Different years - show year for both start and end
	if ( ! start.isSame( end, 'year' ) ) {
		return `${ start.format( 'MMMM D, YYYY' ) } - ${ end.format(
			'MMMM D, YYYY'
		) }`;
	}

	// Same year - show year only for end date.
	return `${ start.format( 'MMMM D' ) } - ${ end.format( 'MMMM D, YYYY' ) }`;
};

const options = Object.keys( timeOptions ).map( ( optionKey ) => {
	const option = timeOptions[ optionKey ];
	return {
		key: optionKey,
		name: option.displayKey,
		hint: formatDateRange( option.start, option.end ),
	};
} );

export const defaultDateRange = {
	date_start: timeOptions.last_7_days.start.format( 'YYYY-MM-DD\\THH:mm:ss' ),
	date_end: timeOptions.last_7_days.end.format( 'YYYY-MM-DD\\THH:mm:ss' ),
};

export const DateRangePicker: React.FC< {
	onDateRangeChange: ( dateRange: DateRange ) => void;
} > = ( { onDateRangeChange } ) => {
	const [ selectedOption, setSelectedOption ] = useState(
		options.find( ( option ) => option.key === 'last_7_days' )
	);

	return (
		<InlineLabelSelect
			label="Select an option"
			options={ options }
			value={ selectedOption }
			placeholder="Select an option..."
			onChange={ ( changes ) => {
				const selectedItem = changes.selectedItem;
				if ( selectedItem ) {
					setSelectedOption( selectedItem );
					onDateRangeChange( {
						date_start: timeOptions[
							selectedItem.key
						].start.format( 'YYYY-MM-DD\\THH:mm:ss' ),
						date_end: timeOptions[ selectedItem.key ].end.format(
							'YYYY-MM-DD\\THH:mm:ss'
						),
					} );
				}
			} }
		/>
	);
};
