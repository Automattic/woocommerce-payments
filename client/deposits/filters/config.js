/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export const filters = [
	{
		label: __( 'Deposit currency', 'woocommerce-payments' ),
		param: 'store_currency_is',
		staticParams: [ 'paged', 'per_page', 'orderby', 'order' ],
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
	// Declare advanced filters here.
];

export const advancedFilters = {
	// Specify advanced filters rules here.
};
