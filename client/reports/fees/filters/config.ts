/**
 * External dependencies
 */
import { __, _x } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { displayMethod, displayType } from '../strings';

export interface FeesFilterEntryType {
	label: string;
	value: string;
}

export interface FeesFilterType {
	label: string;
	param: string;
	staticParams: string[];
	showFilters: () => boolean;
	filters: FeesFilterEntryType[];
}

export interface FeesSummaryForFilters {
	sources?: string[];
	types?: string[];
}

export const feesFilters: FeesFilterType[] = [
	{
		label: __( 'Show', 'woocommerce-payments' ),
		param: 'filter',
		staticParams: [
			'tab',
			'paged',
			'per_page',
			'orderby',
			'order',
			'search',
			'date_before',
			'date_after',
			'date_between',
			'payment_method_type',
			'type',
			'order_id',
			'deposit_id',
			'customer_email',
		],
		showFilters: () => true,
		filters: [
			{
				label: __( 'All fees', 'woocommerce-payments' ),
				value: 'all',
			},
			{
				label: __( 'Advanced filters', 'woocommerce-payments' ),
				value: 'advanced',
			},
		],
	},
];

export const getFeesFilters = (): FeesFilterType[] => feesFilters;

const fallbackTypeOptions = Object.entries( displayType ).map(
	( [ type, label ] ) => ( {
		label,
		value: type,
	} )
);

const getMethodOptions = ( sources?: string[] ): FeesFilterEntryType[] =>
	( sources || [] ).map( ( source ) => ( {
		label: displayMethod( source ),
		value: source,
	} ) );

const getTypeOptions = ( types?: string[] ): FeesFilterEntryType[] =>
	( types && types.length ? types : Object.keys( displayType ) ).map(
		( type ) => ( {
			label: displayType[ type as keyof typeof displayType ] || type,
			value: type,
		} )
	);

export const getFeesFilterOptionsFromSummary = (
	summary: FeesSummaryForFilters = {}
): {
	methodOptions: FeesFilterEntryType[];
	typeOptions: FeesFilterEntryType[];
} => ( {
	methodOptions: getMethodOptions( summary.sources ),
	typeOptions: summary.types?.length
		? getTypeOptions( summary.types )
		: fallbackTypeOptions,
} );

/* eslint-disable max-len */
export const getFeesAdvancedFilters = (
	methodOptions: FeesFilterEntryType[],
	typeOptions: FeesFilterEntryType[]
): any => ( {
	title: __( 'Fees match <select /> filters', 'woocommerce-payments' ),
	filters: {
		date: {
			labels: {
				add: __( 'Date', 'woocommerce-payments' ),
				remove: __( 'Remove fee date filter', 'woocommerce-payments' ),
				rule: __(
					'Select a fee date filter match',
					'woocommerce-payments'
				),
				title: __(
					'<title>Date</title> <rule /> <filter />',
					'woocommerce-payments'
				),
				filter: __( 'Select a fee date', 'woocommerce-payments' ),
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
		payment_method: {
			labels: {
				add: __( 'Method', 'woocommerce-payments' ),
				remove: __(
					'Remove payment method filter',
					'woocommerce-payments'
				),
				rule: __(
					'Select a payment method filter match',
					'woocommerce-payments'
				),
				title: __(
					'<title>Method</title> <rule /> <filter />',
					'woocommerce-payments'
				),
				filter: __( 'Select a payment method', 'woocommerce-payments' ),
			},
			rules: [
				{
					value: 'type',
					label: _x( 'Is', 'payment method', 'woocommerce-payments' ),
				},
			],
			input: {
				component: 'SelectControl',
				options: methodOptions,
			},
		},
		type: {
			labels: {
				add: __( 'Type', 'woocommerce-payments' ),
				remove: __( 'Remove fee type filter', 'woocommerce-payments' ),
				rule: __(
					'Select a fee type filter match',
					'woocommerce-payments'
				),
				title: __(
					'<title>Type</title> <rule /> <filter />',
					'woocommerce-payments'
				),
				filter: __( 'Select a fee type', 'woocommerce-payments' ),
			},
			rules: [
				{
					value: '',
					label: _x( 'Is', 'fee type', 'woocommerce-payments' ),
				},
			],
			input: {
				component: 'SelectControl',
				options: typeOptions,
			},
		},
	},
} );
/* eslint-enable max-len */
