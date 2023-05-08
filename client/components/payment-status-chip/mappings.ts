/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { objectEntries } from 'ts-extras';

/**
 * Internal dependencies
 */
import disputeStatuses from 'components/dispute-status-chip/mappings';
import type { ChipType } from '../chip';

type DisputeStatus = keyof typeof disputeStatuses;
// prettier-ignore
// eslint-disable-next-line
type DisputedStatus = `disputed_${ DisputeStatus }`;

const formattedDisputeStatuses = objectEntries( disputeStatuses ).reduce(
	( statuses, [ status, mapping ] ) => {
		statuses[ `disputed_${ status }` as const ] = {
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
	{} as Record<
		DisputedStatus,
		{ type: ChipType; message: string }
	>
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
	fraud_outcome_review: {
		type: 'warning',
		message: __( 'Needs review', 'woocommerce-payments' ),
	},
	fraud_outcome_block: {
		type: 'alert',
		message: __( 'Payment blocked', 'woocommerce-payments' ),
	},
	...formattedDisputeStatuses,
} as const;
