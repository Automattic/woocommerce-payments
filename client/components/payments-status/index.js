/** @format */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import GridiconCheckmarkCircle from 'gridicons/dist/checkmark-circle';
import GridiconNotice from 'gridicons/dist/notice';

/**
 * Internal dependencies
 */
import 'components/account-status/shared.scss';

const PaymentsStatus = ( props ) => {
	const { paymentsEnabled, iconSize } = props;
	let className;
	let description;
	let icon;

	if ( paymentsEnabled ) {
		description = __( 'Enabled', 'woocommerce-payments' );
		icon = <GridiconCheckmarkCircle size={ iconSize } />;
		className = 'account-status__info__green';
	} else {
		description = __( 'Disabled', 'woocommerce-payments' );
		icon = <GridiconNotice size={ iconSize } />;
		className = 'account-status__info__red';
	}

	return (
		<span className={ className }>
			{ icon }
			{ description }
		</span>
	);
};

export default PaymentsStatus;
