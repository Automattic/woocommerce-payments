/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

const transactionStatusMapping = {
	allow: {
		type: 'primary',
		message: __( 'Succeeded', 'woocommerce-payments' ),
	},
	review: {
		type: 'warning',
		message: __( 'Needs review', 'woocommerce-payments' ),
	},
	block: {
		type: 'alert',
		message: __( 'Payment blocked', 'woocommerce-payments' ),
	},
} as const;

export type TransactionStatus = keyof typeof transactionStatusMapping;

export default transactionStatusMapping;
