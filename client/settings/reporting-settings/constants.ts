/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export const exportLanguageOptions = [
	{
		label: __( 'English (United States)', 'woocommerce-payments' ),
		value: 'en_US',
	},
	{
		label:
			__( 'Site Language - ', 'woocommerce-payments' ) +
			wcpaySettings.locale.native_name,
		value: wcpaySettings.locale.code,
	},
];
