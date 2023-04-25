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

const PaymentsStatusEnabled = ( props ) => {
	const { iconSize } = props;

	return (
		<span className={ 'account-status__info__green' }>
			<GridiconCheckmarkCircle size={ iconSize } />
			{ __( 'Enabled', 'woocommerce-payments' ) }
		</span>
	);
};

const PaymentsStatusDisabled = ( props ) => {
	const { iconSize } = props;

	return (
		<span className={ 'account-status__info__red' }>
			<GridiconNotice size={ iconSize } />
			{ __( 'Disabled', 'woocommerce-payments' ) }
		</span>
	);
};

const PaymentsStatusPending = ( props ) => {
	const { iconSize } = props;

	return (
		<span className={ 'account-status__info__gray' }>
			<GridiconNotice size={ iconSize } />
			{ __( 'Pending verification', 'woocommerce-payments' ) }
		</span>
	);
};

const PaymentsStatus = ( props ) => {
	const { paymentsEnabled, accountStatus } = props;

	if ( 'pending_verification' === accountStatus ) {
		return <PaymentsStatusPending { ...props } />;
	}
	return paymentsEnabled ? (
		<PaymentsStatusEnabled { ...props } />
	) : (
		<PaymentsStatusDisabled { ...props } />
	);
};

export default PaymentsStatus;
