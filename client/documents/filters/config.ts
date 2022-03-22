/**
 * External dependencies
 */
import { __, _x } from '@wordpress/i18n';

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

/*eslint-disable max-len*/
export const advancedFilters = {
	/** translators: A sentence describing filters for Documents. */
	title: __( 'Documents match {{select /}} filters', 'woocommerce-payments' ),
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
				title: __(
					'{{title}}Date{{/title}} {{rule /}} {{filter /}}',
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
				title: __(
					'{{title}}Type{{/title}} {{rule /}} {{filter /}}',
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
