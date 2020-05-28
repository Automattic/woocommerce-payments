/** @format **/
/* eslint-disable camelcase */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export const transactionTypes = {
	charge: __( 'Charge', 'woocommerce-payments' ),
	payment: __( 'Payment', 'woocommerce-payments' ),
	payment_failure_refund: __( 'Payment failure refund', 'woocommerce-payments' ),
	payment_refund: __( 'Payment refund', 'woocommerce-payments' ),
	refund: __( 'Refund', 'woocommerce-payments' ),
	refund_failure: __( 'Refund failure', 'woocommerce-payments' ),
	dispute: __( 'Dispute', 'woocommerce-payments' ),
	dispute_reversal: __( 'Dispute reversal', 'woocommerce-payments' ),
};

export const transactionTypesOptions = Object.entries( transactionTypes ).map(
	( [ type, label ] ) => ( { label: label, value: type } )
);
