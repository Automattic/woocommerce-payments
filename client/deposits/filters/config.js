/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export const filters = () => [
	{
		label: __( 'Deposit currency', 'woocommerce-payments' ),
		param: 'currency_is',
		staticParams: [ 'paged', 'per_page' ],
		showFilters: () => true,
		filters: [
			{
				label: __( 'All', 'woocommerce-payments' ),
				value: 'all',
			},
			...wcpaySettings.currencies.supported.map( ( value ) => ( {
				label: wcpaySettings.currencies.names[ value ],
				value: value,
			} ) ),
		],
	},
	// Declare advanced filters here.
];

export const advancedFilters = () => ( {
	// Specify advanced filters rules here.
} );
