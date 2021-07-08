/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import disputeStatuses from 'components/dispute-status-chip/mappings';

const formattedDisputeStatuses = Object.entries( disputeStatuses ).reduce(
	( statuses, [ status, mapping ] ) => {
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

/* TODO: implement other payment statuses (SCA and authorizations) */
export default {
	refunded_partial: {
		type: 'light',
		message: __( 'Partial refund', 'woocommerce-payments' ),
	},
	refunded_full: {
		type: 'light',
		message: __( 'Refunded', 'woocommerce-payments' ),
	},
	paid: {
		type: 'light',
		message: __( 'Paid', 'woocommerce-payments' ),
	},
	authorized: {
		type: 'primary',
		message: __( 'Payment authorized', 'woocommerce-payments' ),
	},
	failed: {
		type: 'alert',
		message: __( 'Payment failed', 'woocommerce-payments' ),
	},
	blocked: {
		type: 'alert',
		message: __( 'Payment blocked', 'woocommerce-payments' ),
	},
	...formattedDisputeStatuses,
};
