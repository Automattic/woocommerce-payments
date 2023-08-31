/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import type { ChipType } from '../chip';

const status: {
	[ key: string ]: {
		type: ChipType;
		message: string;
	};
} = {
	warning_needs_response: {
		type: 'warning',
		message: __( 'Inquiry: Needs response', 'woocommerce-payments' ),
	},
	warning_under_review: {
		type: 'primary',
		message: __( 'Inquiry: Under review', 'woocommerce-payments' ),
	},
	warning_closed: {
		type: 'light',
		message: __( 'Inquiry: Closed', 'woocommerce-payments' ),
	},
	needs_response: {
		type: 'warning',
		message: __( 'Needs response', 'woocommerce-payments' ),
	},
	under_review: {
		type: 'primary',
		message: __( 'Under review', 'woocommerce-payments' ),
	},
	charge_refunded: {
		type: 'light',
		message: __( 'Charge refunded', 'woocommerce-payments' ),
	},
	won: {
		type: 'success',
		message: __( 'Won', 'woocommerce-payments' ),
	},
	lost: {
		type: 'light',
		message: __( 'Lost', 'woocommerce-payments' ),
	},
};

export default status;
