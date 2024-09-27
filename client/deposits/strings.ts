/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */

import type { DepositStatus } from 'wcpay/types/deposits';

export const displayType = {
	deposit: __( 'Deposit', 'woocommerce-payments' ),
	withdrawal: __( 'Withdrawal', 'woocommerce-payments' ),
};

/**
 * Display status strings for each deposit status.
 *
 * 'deducted' is a display status representing a deposit of the type 'withdrawal' and status 'paid'.
 */
export const displayStatus: Record< DepositStatus | 'deducted', string > = {
	paid: __( 'Paid', 'woocommerce-payments' ),
	deducted: __( 'Deducted', 'woocommerce-payments' ),
	pending: __( 'Pending', 'woocommerce-payments' ),
	in_transit: __( 'In transit', 'woocommerce-payments' ),
	canceled: __( 'Canceled', 'woocommerce-payments' ),
	failed: __( 'Failed', 'woocommerce-payments' ),
};
