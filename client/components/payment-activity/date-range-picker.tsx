/**
 * External dependencies
 */
import React from 'react';
import moment from 'moment';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
//import CustomSelectControl from '../custom-select-control';
import InlineLabelSelect from '../inline-label-select';

type DateRangePickerProps = {
	sendDateRangeToParent: ( dateRange: {
		date_start: string;
		date_end: string;
	} ) => void;
};

export const DateRangePicker: React.FC< DateRangePickerProps > = ( {
	sendDateRangeToParent,
} ) => {
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
	};
	const options = Object.keys( timeOptions ).map( ( optionKey ) => {
		const option = timeOptions[ optionKey ];
		return {
			key: optionKey,
			name: option.displayKey,
			hint: `${ option.start.format(
				'MMMM D, YYYY'
			) } - ${ option.end.format( 'MMMM D, YYYY' ) }`, //replace with function formatHint,
		};
	} );
	const selected = options[ 1 ];

	const handleDateRangeSelectorChange = () => {
		const selectedDateRange = {
			// Subtract 7 days from the current date.
			date_start: moment()
				.subtract( 7, 'd' )
				.format( 'YYYY-MM-DD\\THH:mm:ss' ),
			date_end: moment().format( 'YYYY-MM-DD\\THH:mm:ss' ),
		};
		sendDateRangeToParent( selectedDateRange );
	};

	return (
		<InlineLabelSelect
			label="Select an option"
			options={ options }
			value={ selected }
			placeholder="Select an option..."
			onChange={ handleDateRangeSelectorChange }
		/>
	);
};
