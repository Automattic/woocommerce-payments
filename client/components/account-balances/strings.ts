/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export const greetingStrings = {
	withName: {
		/** translators: %s name of the person being greeted. */
		morning: __( 'Good morning, %s', 'woocommerce-payments' ),
		/** translators: %s name of the person being greeted. */
		afternoon: __( 'Good afternoon, %s', 'woocommerce-payments' ),
		/** translators: %s name of the person being greeted. */
		evening: __( 'Good evening, %s', 'woocommerce-payments' ),
	},
	withoutName: {
		morning: __( 'Good morning', 'woocommerce-payments' ),
		afternoon: __( 'Good afternoon', 'woocommerce-payments' ),
		evening: __( 'Good evening', 'woocommerce-payments' ),
	},
};

export const fundLabelStrings = {
	available: __( 'Available funds', 'woocommerce-payments' ),
	pending: __( 'Pending funds', 'woocommerce-payments' ),
	reserved: __( 'Reserved funds', 'woocommerce-payments' ),
};

/** translators: %s is the currency code, e.g. USD. */
export const currencyBalanceString = __( '%s Balance', 'woocommerce-payments' );
