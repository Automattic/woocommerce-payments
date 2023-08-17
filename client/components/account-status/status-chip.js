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
	if ( accountStatus === 'complete' ) {
		description = __( 'Complete', 'woocommerce-payments' );
		type = 'success';
	} else if ( accountStatus === 'enabled' ) {
		description = __( 'Enabled', 'woocommerce-payments' );
		type = 'primary';
	} else if ( accountStatus === 'restricted_soon' ) {
		description = __( 'Restricted soon', 'woocommerce-payments' );
		type = 'warning';
	} else if (
		accountStatus === 'pending_verification' ||
		( poEnabled && ! poComplete && accountStatus === 'restricted' )
	) {
		description = __( 'Pending', 'woocommerce-payments' );
		type = 'light';
		tooltip = __(
			'Deposits are pending while Stripe verifies details on your account.',
			'woocommerce-payments'
		);
	} else if ( accountStatus === 'restricted_partially' ) {
		description = __( 'Restricted partially', 'woocommerce-payments' );
		type = 'warning';
	} else if ( accountStatus === 'restricted' ) {
		description = __( 'Restricted', 'woocommerce-payments' );
		type = 'alert';
	} else if ( accountStatus.startsWith( 'rejected' ) ) {
		description = __( 'Rejected', 'woocommerce-payments' );
		type = 'light';
	}

	return <Chip message={ description } type={ type } tooltip={ tooltip } />;
};

export default StatusChip;
