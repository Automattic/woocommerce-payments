/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { getChargeStatus } from '../../utils/charge';
import Chip from '../chip';

/* TODO: implement other payment statuses */
const statuses = {
	// eslint-disable-next-line camelcase
	refunded_partial: {
		type: 'light',
		message: __( 'Partial refund', 'woocommerce-payments' ),
	},
	// eslint-disable-next-line camelcase
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
	// eslint-disable-next-line camelcase
	disputed_needs_response: {
		type: 'primary',
		message: __( 'Disputed: Needs response', 'woocommerce-payments' ),
	},
	// eslint-disable-next-line camelcase
	disputed_under_review: {
		type: 'light',
		message: __( 'Disputed: In review', 'woocommerce-payments' ),
	},
	// eslint-disable-next-line camelcase
	disputed_won: {
		type: 'light',
		message: __( 'Disputed: Won', 'woocommerce-payments' ),
	},
	// eslint-disable-next-line camelcase
	disputed_lost: {
		type: 'light',
		message: __( 'Disputed: Lost', 'woocommerce-payments' ),
	},
	default: {
		type: 'light',
		message: '',
	},
};

const PaymentStatusChip = ( props ) => {
	const { charge } = props;
	const status = statuses[ getChargeStatus( charge ) ] || statuses.default;
	return <Chip message={ status.message } type={ status.type } />;
};

export default PaymentStatusChip;
