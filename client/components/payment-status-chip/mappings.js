/** @format **/
/* eslint-disable camelcase */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/* TODO: implement other payment statuses (SCA and authorizations) */
export default {
	refunded_partial: {
		type: 'light',
		message: __( 'Partial refund', 'woocommerce-payments' ),
	},
	refunded_full: {
		type: 'light',
		message: __( 'Refunded', 'woocommerce-payments' ),
	},
	paid: {
		type: 'light',
		message: __( 'Paid', 'woocommerce-payments' ),
	},
	authorized: {
		type: 'primary',
		message: __( 'Payment authorized', 'woocommerce-payments' ),
	},
	failed: {
		type: 'alert',
		message: __( 'Payment failed', 'woocommerce-payments' ),
	},
	blocked: {
		type: 'alert',
		message: __( 'Payment blocked', 'woocommerce-payments' ),
	},
	disputed_needs_response: {
		type: 'primary',
		message: __( 'Disputed: Needs response', 'woocommerce-payments' ),
	},
	disputed_under_review: {
		type: 'light',
		message: __( 'Disputed: In review', 'woocommerce-payments' ),
	},
	disputed_won: {
		type: 'light',
		message: __( 'Disputed: Won', 'woocommerce-payments' ),
	},
	disputed_lost: {
		type: 'light',
		message: __( 'Disputed: Lost', 'woocommerce-payments' ),
	},
	disputed_closed: {
		type: 'light',
		message: __( 'Disputed: Inquiry closed', 'woocommerce-payments' ),
	},
};
