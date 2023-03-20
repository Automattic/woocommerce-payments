/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export default {
	heading: __( 'Deposits', 'woocommerce-payments' ),
	nextDeposit: {
		title: __( 'Next deposits', 'woocommerce-payments' ),
		description: __(
			'The amount may change while payments are still accumulating',
			'woocommerce-payments'
		),
	},
	table_headers: {
		next_deposit_date: __(
			'Estimated dispatch date',
			'woocommerce-payments'
		),
		status: __( 'Status', 'woocommerce-payments' ),
		amount: __( 'Amount', 'woocommerce-payments' ),
	},
};
