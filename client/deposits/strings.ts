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
	deposit: __( 'Payout', 'woocommerce-payments' ),
	withdrawal: __( 'Withdrawal', 'woocommerce-payments' ),
};

/**
 * Labels to display for each deposit status.
 *
 * 'deducted' represents a deposit of the type 'withdrawal' and status 'paid'.
 */
export const depositStatusLabels: Record<
	DepositStatus | 'deducted',
	string
> = {
	paid: __( 'Completed (paid)', 'woocommerce-payments' ),
	deducted: __( 'Completed (deducted)', 'woocommerce-payments' ),
	pending: __( 'Pending', 'woocommerce-payments' ),
	in_transit: __( 'In transit', 'woocommerce-payments' ),
	canceled: __( 'Canceled', 'woocommerce-payments' ),
	failed: __( 'Failed', 'woocommerce-payments' ),
};
