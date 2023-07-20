/** @format */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Chip from 'components/chip';
import './style.scss';

const StatusChip = ( props ) => {
	const { accountStatus, poEnabled, poComplete } = props;

	let description = __( 'Unknown', 'woocommerce-payments' );
	let type = 'light';
	let tooltip = '';
	// Pending status is also shown when the account is PO enabled but not complete and in that case status is restricted.
	if ( 'complete' === accountStatus ) {
		description = __( 'Complete', 'woocommerce-payments' );
		type = 'primary';
	} else if ( 'restricted_soon' === accountStatus ) {
		description = __( 'Restricted soon', 'woocommerce-payments' );
		type = 'warning';
	} else if (
		'pending_verification' === accountStatus ||
		( poEnabled && ! poComplete && 'restricted' === accountStatus )
	) {
		description = __( 'Pending', 'woocommerce-payments' );
		type = 'light';
		tooltip = __(
			'Deposits are pending while Stripe verifies details on your account.',
			'woocommerce-payments'
		);
	} else if ( 'restricted_partially' === accountStatus ) {
		description = __( 'Restricted partially', 'woocommerce-payments' );
		type = 'warning';
	} else if ( 'restricted' === accountStatus ) {
		description = __( 'Restricted', 'woocommerce-payments' );
		type = 'alert';
	} else if ( accountStatus.startsWith( 'rejected' ) ) {
		description = __( 'Rejected', 'woocommerce-payments' );
		type = 'light';
	}

	return <Chip message={ description } type={ type } tooltip={ tooltip } />;
};

export default StatusChip;
