/** @format **/
/* eslint-disable camelcase */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

// Mapping of transaction types to display string.
export const displayType = {
	charge: __( 'Charge', 'woocommerce-payments' ),
	payment: __( 'Payment', 'woocommerce-payments' ),
	payment_failure_refund: __( 'Payment Failure Refund', 'woocommerce-payments' ),
	payment_refund: __( 'Payment Refund', 'woocommerce-payments' ),
	refund: __( 'Refund', 'woocommerce-payments' ),
	refund_failure: __( 'Refund Failure', 'woocommerce-payments' ),
	dispute: __( 'Dispute', 'woocommerce-payments' ),
	dispute_reversal: __( 'Dispute Reversal', 'woocommerce-payments' ),
};
