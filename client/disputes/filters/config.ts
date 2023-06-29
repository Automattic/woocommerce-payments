/**
 * External dependencies
 */
import { __, _x } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';

/**
 * Internal dependencies
 */
import { displayStatus } from 'disputes/strings';

interface DisputesFilterEntryType {
	label: string;
	value: string;
}

export interface DisputesFilterType {
	label: string;
	param: string;
	staticParams: string[];
	showFilters: () => boolean;
	filters: DisputesFilterEntryType[];
	defaultValue?: string;
}

const disputesStatusOptions = Object.entries( displayStatus )
	.map( ( [ status, label ] ) => {
		return { label, value: status };
	} )
	.filter( function ( el ) {
		return el != null;
	} );

export const disputeAwaitingResponseStatuses = [
	'needs_response',
	'warning_needs_response',
];

export const filters: [ DisputesFilterType, DisputesFilterType ] = [
	{
		label: __( 'Dispute currency', 'woocommerce-payments' ),
		param: 'store_currency_is',
		staticParams: [
			'paged',
			'per_page',
			'orderby',
			'order',
			'search',
			'filter',
			'status_is',
			'status_is',
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
				label: __( 'Needs response', 'woocommerce-payments' ),
				value: 'awaiting_response',
			},
			{
				label: __( 'All disputes', 'woocommerce-payments' ),
				value: 'all',
			},
			{
				label: __( 'Advanced filters', 'woocommerce-payments' ),
				value: 'advanced',
			},
		],
	},
];

// TODO: Remove this and all the checks once we drop support of WooCommerce 7.7 and below.
const wooCommerceVersionString = getSetting( 'wcVersion' );
const wooCommerceVersion = parseFloat( wooCommerceVersionString ); // This will parse 7.7.1 to 7.7, but it's fine for this purpose

/*eslint-disable max-len*/
export const advancedFilters = {
	/** translators: A sentence describing filters for Disputes. */
	title:
		wooCommerceVersion > 7.8
			? __( 'Disputes match <select /> filters', 'woocommerce-payments' )
			: __(
					'Disputes match {{select /}} filters',
					'woocommerce-payments'
			  ),
	filters: {
		date: {
			labels: {
				add: __( 'Disputed on date', 'woocommerce-payments' ),
				remove: __(
					'Remove dispute date filter',
					'woocommerce-payments'
				),
				rule: __(
					'Select a dispute date filter match',
					'woocommerce-payments'
				),
				/* translators: A sentence describing a Dispute date filter. */
				title:
					wooCommerceVersion < 7.8
						? __(
								'{{title}}Date{{/title}} {{rule /}} {{filter /}}',
								'woocommerce-payments'
						  )
						: __(
								'<title>Date</title> <rule /> <filter />',
								'woocommerce-payments'
						  ),
				filter: __( 'Select a dispute date', 'woocommerce-payments' ),
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
					'Remove dispute status filter',
					'woocommerce-payments'
				),
				rule: __(
					'Select a dispute status filter match',
					'woocommerce-payments'
				),
				/* translators: A sentence describing a Dispute status filter. */
				title:
					wooCommerceVersion < 7.8
						? __(
								'{{title}}Status{{/title}} {{rule /}} {{filter /}}',
								'woocommerce-payments'
						  )
						: __(
								'<title>Status</title> <rule /> <filter />',
								'woocommerce-payments'
						  ),
				filter: __( 'Select a dispute status', 'woocommerce-payments' ),
			},
			rules: [
				{
					value: 'is',
					/* translators: Sentence fragment, logical, "Is" refers to searching for disputes matching a chosen dispute status. */
					label: _x( 'Is', 'dispute status', 'woocommerce-payments' ),
				},
				{
					value: 'is_not',
					/* translators: Sentence fragment, logical, "Is not" refers to searching for disputes that don\'t match a chosen dispute status. */
					label: _x(
						'Is not',
						'dispute status',
						'woocommerce-payments'
					),
				},
			],
			input: {
				component: 'SelectControl',
				options: disputesStatusOptions,
			},
		},
	},
};
/*eslint-enable max-len*/
