/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

// Mapping of transaction types to display string.
export const displayType = {
	charge: __( 'Charge', 'woocommerce-payments' ),
	payment: __( 'Payment', 'woocommerce-payments' ),
	payment_failure_refund: __(
		'Payment failure refund',
		'woocommerce-payments'
	),
	payment_refund: __( 'Payment refund', 'woocommerce-payments' ),
	refund: __( 'Refund', 'woocommerce-payments' ),
	refund_failure: __( 'Refund failure', 'woocommerce-payments' ),
	dispute: __( 'Dispute', 'woocommerce-payments' ),
	dispute_reversal: __( 'Dispute reversal', 'woocommerce-payments' ),
	card_reader_fee: __( 'Reader fee', 'woocommerce-payments' ),
	financing_payout: __( 'Loan disbursement', 'woocommerce-payments' ),
	financing_paydown: __( 'Loan repayment', 'woocommerce-payments' ),
};

// Mapping of transaction device type string.
export const sourceDevice = {
	android: __( 'Android', 'woocommerce-payments' ),
	ios: __( 'iPhone', 'woocommerce-payments' ),
};

// Mapping of transaction channel type string.
export const channel = {
	online: __( 'Online', 'woocommerce-payments' ),
	in_person: __( 'In-Person', 'woocommerce-payments' ),
};

// Mapping of transaction risk level string.
export const riskLevel = {
	'0': __( 'Normal', 'woocommerce-payments' ),
	'1': __( 'Elevated', 'woocommerce-payments' ),
	'2': __( 'Highest', 'woocommerce-payments' ),
};
