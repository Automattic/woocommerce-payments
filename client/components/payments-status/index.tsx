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

const PaymentsStatusEnabled = ( { iconSize }: PaymentsStatusProps ) => {
	return (
		<span className={ 'account-status__info__green' }>
			<GridiconCheckmarkCircle size={ iconSize } />
			{ __( 'Enabled', 'woocommerce-payments' ) }
		</span>
	);
};

const PaymentsStatusDisabled = ( { iconSize }: PaymentsStatusProps ) => {
	return (
		<span className={ 'account-status__info__red' }>
			<GridiconNotice size={ iconSize } />
			{ __( 'Disabled', 'woocommerce-payments' ) }
		</span>
	);
};

const PaymentsStatusPending = ( { iconSize }: PaymentsStatusProps ) => {
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

const PaymentsStatus = ( {
	paymentsEnabled,
	accountStatus,
	iconSize,
}: Props ) => {
	if ( paymentsEnabled ) {
		return <PaymentsStatusEnabled iconSize={ iconSize } />;
	}

	return accountStatus === 'pending_verification' ? (
		<PaymentsStatusPending iconSize={ iconSize } />
	) : (
		<PaymentsStatusDisabled iconSize={ iconSize } />
	);
};

export default PaymentsStatus;
