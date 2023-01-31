/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

const transactionStatusMapping = {
	succeeded: {
		type: 'primary',
		message: __( 'Succeeded', 'woocommerce-payments' ),
	},
	on_review: {
		type: 'warning',
		message: __( 'Needs review', 'woocommerce-payments' ),
	},
	blocked: {
		type: 'alert',
		message: __( 'Blocked', 'woocommerce-payments' ),
	},
};

export type TransactionStatus = keyof typeof transactionStatusMapping;

export default transactionStatusMapping;
