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
};

export const documentationUrls = {
	depositSchedule:
		'https://woocommerce.com/document/woopayments/deposits/deposit-schedule/',
	negativeBalance:
		'https://woocommerce.com/document/woopayments/fees-and-debits/account-showing-negative-balance/',
};
