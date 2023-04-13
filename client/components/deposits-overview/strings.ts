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
	notices: {
		depositIncludesLoan: __(
			'This deposit will include funds from your WooCommerce Capital loan.',
			'woocommerce-payments'
		),
		businessDayDelay: __(
			'Deposits pending or in-transit may take 1-2 business days to appear in your bank account once dispatched',
			'woocommerce-payments'
		),
		negativeBalance: __(
			'Deposits may be interrupted while your WooCommerce Payments balance remains negative.',
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
	depositHistoryHeading: __( 'Deposit history', 'woocommerce-payments' ),
	documentationUrls: {
		capital:
			'https://woocommerce.com/document/woocommerce-payments/stripe-capital/overview',
	},
};
