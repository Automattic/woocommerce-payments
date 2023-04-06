/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export default {
	heading: __( 'Deposits', 'woocommerce-payments' ),
	nextDeposit: {
		title: __( 'Next deposit', 'woocommerce-payments' ),
		description: __(
			'The amount may change while payments are still accumulating',
			'woocommerce-payments'
		),
	},
	viewAllDeposits: __( 'View full deposits history', 'woocommerce-payments' ),
	changeDepositSchedule: __(
		'Change deposit schedule',
		'woocommerce-payments'
	),
	tableHeaders: {
		nextDepositDate: __(
			'Estimated dispatch date',
			'woocommerce-payments'
		),
		recentDepositDate: __( 'Dispatch date', 'woocommerce-payments' ),
		status: __( 'Status', 'woocommerce-payments' ),
		amount: __( 'Amount', 'woocommerce-payments' ),
	},
};
