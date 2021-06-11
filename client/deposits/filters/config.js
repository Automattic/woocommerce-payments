/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export const filters = [
	{
		label: __( 'Deposit currency', 'woocommerce-payments' ),
		param: 'store_currency_is',
		staticParams: [
			'paged',
			'per_page',
			'orderby',
			'order',
			'date_before',
			'date_after',
			'date_between',
		],
		showFilters: () => false,
		filters: [
			{
				label: __( 'All', 'woocommerce-payments' ),
				value: '---',
			},
			// Other values are getting injected later, taking values from store.
		],
		defaultValue: '---',
	},
	{
		label: __( 'Show', 'woocommerce-payments' ),
		param: 'filter',
		staticParams: [
			'paged',
			'per_page',
			'orderby',
			'order',
			'date_before',
			'date_after',
			'date_between',
		],
		showFilters: () => true,
		filters: [
			{
				label: __( 'All deposits', 'woocommerce-payments' ),
				value: 'all',
			},
			{
				label: __( 'Advanced filters', 'woocommerce-payments' ),
				value: 'advanced',
			},
		],
	},
	// Declare advanced filters here.
];

export const advancedFilters = {
	/** translators: A sentence describing filters for deposits. See screen shot for context: https://d.pr/i/NcGpwL */
	title: __( 'Deposits match {{select /}} filters', 'woocommerce-payments' ),
	filters: {
		date: {
			labels: {
				add: __( 'Date', 'woocommerce-payments' ),
				remove: __(
					'Remove deposit date filter',
					'woocommerce-payments'
				),
				rule: __(
					'Select a deposit date filter match',
					'woocommerce-payments'
				),
				/* translators: A sentence describing a deposit date filter. See screen shot for context: https://d.pr/i/NcGpwL */
				title: __(
					'{{title}}Date{{/title}} {{rule /}} {{filter /}}',
					'woocommerce-payments'
				),
				filter: __( 'Select a deposit date', 'woocommerce-payments' ),
			},
			rules: [
				{
					value: 'before',
					label: __( 'Before', 'woocommerce-payments' ),
				},
				{
					value: 'after',
					label: __( 'After', 'woocommerce-payments' ),
				},
				{
					value: 'between',
					label: __( 'Between', 'woocommerce-payments' ),
				},
			],
			input: {
				component: 'Date',
			},
		},
	},
};
