/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */

import type { DepositStatus } from 'wcpay/types/deposits';

// Content for test mode notice.
export const notice = {
	content: __(
		'Viewing test deposits. To view live disputes, disable test mode in ',
		'woocommerce-payments'
	),
	action: sprintf(
		/* translators: %s: WooPayments */
		__( '%s settings.', 'woocommerce-payments' ),
		'WooPayments'
	),
	details: sprintf(
		/* translators: %s: WooPayments */
		__(
			'%s was in test mode when these orders were placed.',
			'woocommerce-payments'
		),
		'WooPayments'
	),
};

export const displayType = {
	deposit: __( 'Deposit', 'woocommerce-payments' ),
	withdrawal: __( 'Withdrawal', 'woocommerce-payments' ),
};

export const displayStatus: Record< DepositStatus, string > = {
	paid: __( 'Paid', 'woocommerce-payments' ),
	pending: __( 'Pending', 'woocommerce-payments' ),
	in_transit: __( 'In transit', 'woocommerce-payments' ),
	canceled: __( 'Canceled', 'woocommerce-payments' ),
	failed: __( 'Failed', 'woocommerce-payments' ),
};
