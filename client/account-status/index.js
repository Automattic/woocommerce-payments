/** @format */
/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';
import GridiconCheckmarkCircle from 'gridicons/dist/checkmark-circle';
import GridiconNotice from 'gridicons/dist/notice';

/**
 * Internal dependencies
 */
import Chip from 'components/chip';
import './style.scss';

const renderStatusChip = ( status ) => {
	let description = __( 'Unknown', 'woocommerce-payments' );
	let type = 'light';
	if ( status === 'complete' ) {
		description = __( 'Complete', 'woocommerce-payments' );
		type = 'primary';
	} else if ( status === 'restricted_soon' ) {
		description = __( 'Restricted soon', 'woocommerce-payments' );
		type = 'warning';
	} else if ( status === 'restricted' ) {
		description = __( 'Restricted', 'woocommerce-payments' );
		type = 'alert';
	} else if ( status.startsWith( 'rejected' ) ) {
		description = __( 'Rejected', 'woocommerce-payments' );
		type = 'light';
	}

	return <Chip message={ description } type={ type } isCompact />;
};

const renderPaymentsStatus = ( paymentsEnabled ) => {
	let className;
	let description;
	let icon;

	if ( paymentsEnabled ) {
		description = __( 'Enabled', 'woocommerce-payments' );
		icon = <GridiconCheckmarkCircle size={ 18 } />;
		className = 'account-status__info__green';
	} else {
		description = __( 'Disabled', 'woocommerce-payments' );
		icon = <GridiconNotice size={ 18 } />;
		className = 'account-status__info__red';
	}

	return (
		<span className="account-status__info">
			{ __( 'Payments:', 'woocommerce-payments' ) }
			<span className={ className }>
				{ icon }
				{ description }
			</span>
		</span>
	);
};

const renderDepositsStatus = ( depositsStatus ) => {
	let className = 'account-status__info__green';
	let description;
	let icon = <GridiconCheckmarkCircle size={ 18 } />;

	if ( depositsStatus === 'disabled' ) {
		description = __( 'Disabled', 'woocommerce-payments' );
		className = 'account-status__info__red';
		icon = <GridiconNotice size={ 18 } />;
	} else if ( depositsStatus === 'daily' ) {
		description = __( 'Daily', 'woocommerce-payments' );
	} else if ( depositsStatus === 'weekly' ) {
		description = __( 'Weekly', 'woocommerce-payments' );
	} else if ( depositsStatus === 'monthly' ) {
		description = __( 'Monthly', 'woocommerce-payments' );
	} else if ( depositsStatus === 'manual' ) {
		description = __( 'Manual', 'woocommerce-payments' );
	} else {
		description = __( 'Unknown', 'woocommerce-payments' );
	}

	return (
		<span className="account-status__info">
			{ __( 'Deposits:', 'woocommerce-payments' ) }
			<span className={ className }>
				{ icon }
				{ description }
			</span>
		</span>
	);
};

const renderAccountStatusDescription = ( accountStatus ) => {
	const { status, currentDeadline, pastDue, accountLink } = accountStatus;
	if ( status === 'complete' ) {
		return '';
	}

	let description = '';
	if ( status === 'restricted_soon' ) {
		description = createInterpolateElement(
			sprintf(
				/* translators: %s - formatted requirements current deadline, <a> - dashboard login URL */
				__(
					'To avoid disrupting deposits, <a>update this account</a> by %s with more information about the business.',
					'woocommerce-payments'
				),
				dateI18n(
					'ga M j, Y',
					moment( currentDeadline * 1000 ).toISOString()
				)
			),
			// eslint-disable-next-line jsx-a11y/anchor-has-content
			{ a: <a href={ accountLink } /> }
		);
	} else if ( status === 'restricted' && pastDue ) {
		description = createInterpolateElement(
			/* translators: <a> - dashboard login URL */
			__(
				'Payments and deposits are disabled for this account until missing business information is updated. <a>Update now</a>',
				'woocommerce-payments'
			),
			// eslint-disable-next-line jsx-a11y/anchor-has-content
			{ a: <a href={ accountLink } /> }
		);
	} else if ( status === 'restricted' ) {
		description = __(
			'Payments and deposits are disabled for this account until business information is verified by the payment processor.',
			'woocommerce-payments'
		);
	} else if ( status === 'rejected.fraud' ) {
		description = __(
			'This account has been rejected because of suspected fraudulent activity.',
			'woocommerce-payments'
		);
	} else if ( status === 'rejected.terms_of_service' ) {
		description = __(
			'This account has been rejected due to a Terms of Service violation.',
			'woocommerce-payments'
		);
	} else if ( status.startsWith( 'rejected' ) ) {
		description = __(
			'This account has been rejected.',
			'woocommerce-payments'
		);
	}

	if ( ! description ) {
		return null;
	}

	return <div className="account-status__desc">{ description }</div>;
};

const AccountStatus = ( props ) => {
	const { accountStatus } = props;
	if ( accountStatus.error ) {
		return <div>{ __( 'Error determining the connection status.' ) }</div>;
	}

	return (
		<div>
			<div>
				{ renderStatusChip( accountStatus.status ) }
				{ renderPaymentsStatus( accountStatus.paymentsEnabled ) }
				{ renderDepositsStatus( accountStatus.depositsStatus ) }
			</div>
			{ renderAccountStatusDescription( accountStatus ) }
		</div>
	);
};

export default AccountStatus;
