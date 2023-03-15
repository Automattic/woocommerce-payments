/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export const fundLabelStrings = {
	available: __( 'Available funds', 'woocommerce-payments' ),
	pending: __( 'Pending funds', 'woocommerce-payments' ),
	reserve: __( 'Reserve funds', 'woocommerce-payments' ),
};

// Translators: %s is the currency code, e.g. USD.
export const currencyBalanceString = __( '%s Balance', 'woocommerce-payments' );
