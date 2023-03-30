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
		status: __( 'Status', 'woocommerce-payments' ),
		amount: __( 'Amount', 'woocommerce-payments' ),
	},
	depositHistory: {
		title: __( 'Deposit history', 'woocommerce-payments' ),
		descriptions: {
			/** translators: {{strong}}: placeholders are opening and closing strong tags. {{suspendLink}}: is a <a> link element */
			suspended: __(
				'Your deposits are {{strong}}temporarily suspended{{/strong}}. {{suspendLink}}Learn more{{/suspendLink}}',
				'woocommerce-payments'
			),
			/** translators: {{strong}}: placeholders are opening and closing strong tags. */
			daily: __(
				'Your deposits are dispatched {{strong}}automatically every day{{/strong}}',
				'woocommerce-payments'
			),
			/** translators: %s: is the day of the week. eg "Friday". {{strong}}: placeholders are opening and closing strong tags.*/
			weekly: __(
				'Your deposits are dispatched {{strong}}automatically every %s{{/strong}}',
				'woocommerce-payments'
			),
			/** translators: %s: is the day of the month. eg "15th". {{strong}}: placeholders are opening and closing strong tags.*/
			monthly: __(
				'Your deposits are dispatched {{strong}}automatically on the %s of every month{{/strong}}',
				'woocommerce-payments'
			),
			/** translators: {{strong}}: placeholders are opening and closing strong tags. */
			lastDayOfMonth: __(
				'Your deposits are dispatched {{strong}}automatically on the last day of every month{{/strong}}',
				'woocommerce-payments'
			),
		},
	},
};
