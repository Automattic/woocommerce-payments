/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export default {
	warning_needs_response: {
		type: 'primary',
		message: __( 'Inquiry: Needs response', 'woocommerce-payments' ),
	},
	warning_under_review: {
		type: 'light',
		message: __( 'Inquiry: Under review', 'woocommerce-payments' ),
	},
	warning_closed: {
		type: 'light',
		message: __( 'Inquiry: Closed', 'woocommerce-payments' ),
	},
	needs_response: {
		type: 'primary',
		message: __( 'Needs response', 'woocommerce-payments' ),
	},
	under_review: {
		type: 'light',
		message: __( 'Under review', 'woocommerce-payments' ),
	},
	charge_refunded: {
		type: 'light',
		message: __( 'Charge refunded', 'woocommerce-payments' ),
	},
	won: {
		type: 'light',
		message: __( 'Won', 'woocommerce-payments' ),
	},
	lost: {
		type: 'light',
		message: __( 'Lost', 'woocommerce-payments' ),
	},
};
