/** @format */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import GridiconCheckmarkCircle from 'gridicons/dist/checkmark-circle';
import GridiconNotice from 'gridicons/dist/notice';
import React from 'react';

/**
 * Internal dependencies
 */
import 'components/account-status/shared.scss';
import type { AccountStatus } from 'wcpay/types/account/account-status';

interface PaymentsStatusProps {
	iconSize: number;
}

const PaymentsStatusEnabled: React.FC< PaymentsStatusProps > = ( props ) => {
	const { iconSize } = props;

	return (
		<span className={ 'account-status__info__green' }>
			<GridiconCheckmarkCircle size={ iconSize } />
			{ __( 'Enabled', 'woocommerce-payments' ) }
		</span>
	);
};

const PaymentsStatusDisabled: React.FC< PaymentsStatusProps > = ( props ) => {
	const { iconSize } = props;

	return (
		<span className={ 'account-status__info__red' }>
			<GridiconNotice size={ iconSize } />
			{ __( 'Disabled', 'woocommerce-payments' ) }
		</span>
	);
};

const PaymentsStatusPending: React.FC< PaymentsStatusProps > = ( props ) => {
	const { iconSize } = props;

	return (
		<span className={ 'account-status__info__gray' }>
			<GridiconNotice size={ iconSize } />
			{ __( 'Pending verification', 'woocommerce-payments' ) }
		</span>
	);
};

interface Props {
	paymentsEnabled: string;
	accountStatus: AccountStatus;
	iconSize: number;
}

const PaymentsStatus: React.FC< Props > = ( props ) => {
	const { paymentsEnabled, accountStatus } = props;

	if ( 'pending_verification' === accountStatus ) {
		return <PaymentsStatusPending iconSize={ props.iconSize } />;
	}
	return paymentsEnabled ? (
		<PaymentsStatusEnabled iconSize={ props.iconSize } />
	) : (
		<PaymentsStatusDisabled iconSize={ props.iconSize } />
	);
};

export default PaymentsStatus;
