/**
 * External dependencies
 */
import { __, _x } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { displayStatus } from 'deposits/strings';

const depositTypesOptions = Object.entries(
	displayStatus
).map( ( [ type, label ] ) => ( { label, value: type } ) );

export const filters = [
	{
		label: __( 'Deposit currency', 'woocommerce-payments' ),
		param: 'store_currency_is',
		staticParams: [
			'paged',
			'per_page',
			'orderby',
			'order',
			'filter',
			'date_before',
			'date_after',
			'date_between',
			'status_is',
			'status_is_not',
			'match',
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
			'store_currency_is',
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

/*eslint-disable max-len*/
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
		status: {
			labels: {
				add: __( 'Status', 'woocommerce-payments' ),
				remove: __(
					'Remove deposit status filter',
					'woocommerce-payments'
				),
				rule: __(
					'Select a deposit status filter match',
					'woocommerce-payments'
				),
				/* translators: A sentence describing a deposit status filter. See screen shot for context: https://d.pr/i/NcGpwL */
				title: __(
					'{{title}}Status{{/title}} {{rule /}} {{filter /}}',
					'woocommerce-payments'
				),
				filter: __( 'Select a deposit status', 'woocommerce-payments' ),
			},
			rules: [
				{
					value: 'is',
					/* translators: Sentence fragment, logical, "Is" refers to searching for deposits matching a chosen deposit status. Screenshot for context: https://d.pr/i/NcGpwL */
					label: _x( 'Is', 'deposit status', 'woocommerce-payments' ),
				},
				{
					value: 'is_not',
					/* translators: Sentence fragment, logical, "Is not" refers to searching for deposits that don\'t match a chosen deposit status. Screenshot for context: https://d.pr/i/NcGpwL */
					label: _x(
						'Is not',
						'deposit status',
						'woocommerce-payments'
					),
				},
			],
			input: {
				component: 'SelectControl',
				options: depositTypesOptions,
			},
		},
	},
};
