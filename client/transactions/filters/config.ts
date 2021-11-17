/**
 * External dependencies
 */
import { __, _x } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { displayType } from 'transactions/strings';

interface TransactionsFilterEntryType {
	label: string;
	value: string;
}

export interface TransactionsFilterType {
	label: string;
	param: string;
	staticParams: string[];
	showFilters: () => boolean;
	filters: TransactionsFilterEntryType[];
	defaultValue?: string;
}

const transactionTypesOptions = Object.entries( displayType )
	.map( ( [ type, label ] ) => {
		//@TODO - implement filter transactions by card reader fee
		if ( type === 'card_reader_fee' ) {
			return null;
		}
		return { label, value: type };
	} )
	.filter( function ( el ) {
		return el != null;
	} );

export const filters: [ TransactionsFilterType, TransactionsFilterType ] = [
	{
		label: __( 'Deposit currency', 'woocommerce-payments' ),
		param: 'store_currency_is',
		staticParams: [
			'paged',
			'per_page',
			'orderby',
			'order',
			'search',
			'filter',
			'type_is',
			'type_is_not',
			'date_before',
			'date_after',
			'date_between',
		],
		showFilters: () => false,
		filters: [
			{
				label: __( 'All currencies', 'woocommerce-payments' ),
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
			'search',
			'store_currency_is',
		],
		showFilters: () => true,
		filters: [
			{
				label: __( 'All transactions', 'woocommerce-payments' ),
				value: 'all',
			},
			{
				label: __( 'Advanced filters', 'woocommerce-payments' ),
				value: 'advanced',
			},
		],
	},
];

/*eslint-disable max-len*/
export const advancedFilters = {
	/** translators: A sentence describing filters for Transactions. See screen shot for context: https://d.pr/i/NcGpwL */
	title: __(
		'Transactions match {{select /}} filters',
		'woocommerce-payments'
	),
	filters: {
		date: {
			labels: {
				add: __( 'Date', 'woocommerce-payments' ),
				remove: __(
					'Remove transaction date filter',
					'woocommerce-payments'
				),
				rule: __(
					'Select a transaction date filter match',
					'woocommerce-payments'
				),
				/* translators: A sentence describing a Transaction date filter. See screen shot for context: https://d.pr/i/NcGpwL */
				title: __(
					'{{title}}Date{{/title}} {{rule /}} {{filter /}}',
					'woocommerce-payments'
				),
				filter: __(
					'Select a transaction date',
					'woocommerce-payments'
				),
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
		type: {
			labels: {
				add: __( 'Type', 'woocommerce-payments' ),
				remove: __(
					'Remove transaction type filter',
					'woocommerce-payments'
				),
				rule: __(
					'Select a transaction type filter match',
					'woocommerce-payments'
				),
				/* translators: A sentence describing a Transaction type filter. See screen shot for context: https://d.pr/i/NcGpwL */
				title: __(
					'{{title}}Type{{/title}} {{rule /}} {{filter /}}',
					'woocommerce-payments'
				),
				filter: __(
					'Select a transaction type',
					'woocommerce-payments'
				),
			},
			rules: [
				{
					value: 'is',
					/* translators: Sentence fragment, logical, "Is" refers to searching for transactions matching a chosen transaction type. Screenshot for context: https://d.pr/i/NcGpwL */
					label: _x(
						'Is',
						'transaction type',
						'woocommerce-payments'
					),
				},
				{
					value: 'is_not',
					/* translators: Sentence fragment, logical, "Is not" refers to searching for transactions that don\'t match a chosen transaction type. Screenshot for context: https://d.pr/i/NcGpwL */
					label: _x(
						'Is not',
						'transaction type',
						'woocommerce-payments'
					),
				},
			],
			input: {
				component: 'SelectControl',
				options: transactionTypesOptions,
			},
		},
	},
};
/*eslint-enable max-len*/
