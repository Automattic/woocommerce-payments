/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import disputeStatuses from 'wcpay/components/dispute-status-chip/mappings';
import type { ChipType } from '../chip';

interface StatusMapping {
	type: ChipType;
	message: string;
}

const formattedDisputeStatuses = Object.entries( disputeStatuses ).reduce(
	( statuses: Record< string, StatusMapping >, [ status, mapping ] ) => {
		statuses[ 'disputed_' + status ] = {
			type: mapping.type,
			message: status.startsWith( 'warning_' )
				? mapping.message
				: sprintf(
						/** translators: %s dispute status, e.g. Won, Lost, Under review, etc. */
						__( 'Disputed: %s', 'woocommerce-payments' ),
						mapping.message
				  ),
		};
		return statuses;
	},
	{}
);

const paymentStatusMappings: Record< string, StatusMapping > = {
	refunded_partial: {
		type: 'light',
		message: __( 'Partial refund', 'woocommerce-payments' ),
	},
	refunded_full: {
		type: 'light',
		message: __( 'Refunded', 'woocommerce-payments' ),
	},
	paid: {
		type: 'success',
		message: __( 'Paid', 'woocommerce-payments' ),
	},
	authorized: {
		type: 'primary',
		message: __( 'Payment authorized', 'woocommerce-payments' ),
	},
	authorization_failed: {
		type: 'alert',
		message: __( 'Authorization failed', 'woocommerce-payments' ),
	},
	authorization_expired: {
		type: 'alert',
		message: __( 'Authorization expired', 'woocommerce-payments' ),
	},
	refund_failed: {
		type: 'alert',
		message: __( 'Refund failure', 'woocommerce-payments' ),
	},
	failed: {
		type: 'alert',
		message: __( 'Payment failed', 'woocommerce-payments' ),
	},
	blocked: {
		type: 'alert',
		message: __( 'Payment blocked', 'woocommerce-payments' ),
	},
	canceled: {
		type: 'light',
		message: __( 'Canceled', 'woocommerce-payments' ),
	},
	fraud_outcome_review: {
		type: 'warning',
		message: __( 'Needs review', 'woocommerce-payments' ),
	},
	fraud_outcome_block: {
		type: 'alert',
		message: __( 'Payment blocked', 'woocommerce-payments' ),
	},
	processing: {
		type: 'light',
		message: __( 'Processing', 'woocommerce-payments' ),
	},
	requires_action: {
		type: 'warning',
		message: __( 'Authentication required', 'woocommerce-payments' ),
	},
	requires_confirmation: {
		type: 'light',
		message: __( 'Awaiting confirmation', 'woocommerce-payments' ),
	},
	...formattedDisputeStatuses,
};

export default paymentStatusMappings;
