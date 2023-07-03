/**
 * External dependencies
 */
import { __, _x } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';

/**
 * Internal dependencies
 */
import { displayType } from 'documents/strings';

interface DocumentsFilterEntryType {
	label: string;
	value: string;
}

export interface DocumentsFilterType {
	label: string;
	param: string;
	staticParams: string[];
	showFilters: () => boolean;
	filters: DocumentsFilterEntryType[];
	defaultValue?: string;
}

const documentTypesOptions = Object.entries( displayType )
	.map( ( [ type, label ] ) => {
		return { label, value: type };
	} )
	.filter( function ( el ) {
		return el != null;
	} );

export const filters: [ DocumentsFilterType ] = [
	{
		label: __( 'Show', 'woocommerce-payments' ),
		param: 'filter',
		staticParams: [ 'paged', 'per_page', 'orderby', 'order' ],
		showFilters: () => true,
		filters: [
			{
				label: __( 'All documents', 'woocommerce-payments' ),
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
	/** translators: A sentence describing filters for Documents. */
	title:
		wooCommerceVersion < 7.8
			? __(
					'Documents match {{select /}} filters',
					'woocommerce-payments'
			  )
			: __(
					'Documents match <select /> filters',
					'woocommerce-payments'
			  ),
	filters: {
		date: {
			labels: {
				add: __( 'Date', 'woocommerce-payments' ),
				remove: __(
					'Remove document date filter',
					'woocommerce-payments'
				),
				rule: __(
					'Select a document date filter match',
					'woocommerce-payments'
				),
				/* translators: A sentence describing a Document date filter. */
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
				filter: __( 'Select a document date', 'woocommerce-payments' ),
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
					'Remove document type filter',
					'woocommerce-payments'
				),
				rule: __(
					'Select a document type filter match',
					'woocommerce-payments'
				),
				/* translators: A sentence describing a Document type filter. */
				title:
					wooCommerceVersion < 7.8
						? __(
								'{{title}}Type{{/title}} {{rule /}} {{filter /}}',
								'woocommerce-payments'
						  )
						: __(
								'<title>Type</title> <rule /> <filter />',
								'woocommerce-payments'
						  ),
				filter: __( 'Select a document type', 'woocommerce-payments' ),
			},
			rules: [
				{
					value: 'is',
					/* translators: Sentence fragment, logical, "Is" refers to searching for documents matching a chosen document type. */
					label: _x( 'Is', 'document type', 'woocommerce-payments' ),
				},
				{
					value: 'is_not',
					/* translators: Sentence fragment, logical, "Is not" refers to searching for documents that do not match a chosen document type. */
					label: _x(
						'Is not',
						'document type',
						'woocommerce-payments'
					),
				},
			],
			input: {
				component: 'SelectControl',
				options: documentTypesOptions,
			},
		},
	},
};
/*eslint-enable max-len*/
