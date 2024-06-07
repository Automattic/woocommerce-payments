/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
//import CustomSelectControl from '../custom-select-control';
import InlineLabelSelect from '../inline-label-select';

export const DateRangePicker: React.FC = () => {
	const options = [
		{
			key: 'today',
			name: __( 'Today', 'woocommerce-payments' ),
			hint: 'Coming up later',
		},
		{
			key: 'last_7_days',
			name: __( 'Last 7 days', 'woocommerce-payments' ),
			hint: 'Coming up later',
		},
		{
			key: 'last_4_weeks',
			name: __( 'Last 4 weeks', 'woocommerce-payments' ),
			hint: 'Coming up later',
		},
		{
			key: 'last_3_months',
			name: __( 'Last 3 months', 'woocommerce-payments' ),
			hint: 'Coming up later',
		},
		{
			key: 'last_12_months',
			name: __( 'Last 12 months', 'woocommerce-payments' ),
			hint: 'Coming up later',
		},
		{
			key: 'month_to_date',
			name: __( 'Month to date', 'woocommerce-payments' ),
			hint: 'Coming up later',
		},
		{
			key: 'quarter_to_date',
			name: __( 'Quarter to date', 'woocommerce-payments' ),
			hint: 'Coming up later',
		},
		{
			key: 'year_to_date',
			name: __( 'Year to date', 'woocommerce-payments' ),
			hint: 'Coming up later',
		},
	];
	const selected = options[ 1 ];

	return (
		<InlineLabelSelect
			label="Select an option"
			options={ options }
			value={ selected }
			placeholder="Select an option..."
		/>
	);
};
