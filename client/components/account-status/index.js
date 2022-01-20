/** @format **/

/**
 * External dependencies
 */
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	FlexBlock,
	FlexItem,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import AccountFees from './account-fees';
import AccountStatusItem from './account-status-item';
import DepositsStatus from 'components/deposits-status';
import PaymentsStatus from 'components/payments-status';
import StatusChip from './status-chip';
import './style.scss';
import './shared.scss';

const AccountStatusCard = ( props ) => {
	const { title, children, value } = props;
	return (
		<Card isMedium>
			<CardHeader
				className={ 'woocommerce-account-status__header' }
				direction={ 'row' }
				align={ 'center' }
				justify={ 'left' }
				gap={ 3 }
				expanded
			>
				{ title }
			</CardHeader>
			<CardBody>{ children || value || null }</CardBody>
		</Card>
	);
};

const AccountStatusError = () => {
	const cardTitle = __( 'Account details', 'woocommerce-payments' );
	return (
		<AccountStatusCard title={ cardTitle }>
			{ __(
				'Error determining the connection status.',
				'woocommerce-payments'
			) }
		</AccountStatusCard>
	);
};

const AccountStatusDetails = ( props ) => {
	const { accountStatus, accountFees } = props;

	const cardTitle = (
		<>
			<FlexItem className={ 'account-details' }>
				{ __( 'Account details', 'woocommerce-payments' ) }
			</FlexItem>
			<FlexBlock className={ 'account-status' }>
				<StatusChip accountStatus={ accountStatus.status } />
			</FlexBlock>
			<FlexItem className={ 'edit-details' }>
				<Button isLink href={ accountStatus.accountLink }>
					{ __( 'Edit details', 'woocommerce-payments' ) }
				</Button>
			</FlexItem>
		</>
	);

	return (
		<AccountStatusCard title={ cardTitle }>
			<AccountStatusItem
				label={ __( 'Payments:', 'woocommerce-payments' ) }
			>
				<PaymentsStatus
					paymentsEnabled={ accountStatus.paymentsEnabled }
				/>
			</AccountStatusItem>
			<AccountStatusItem
				label={ __( 'Deposits:', 'woocommerce-payments' ) }
			>
				<DepositsStatus
					depositsStatus={ accountStatus.depositsStatus }
				/>
			</AccountStatusItem>
			{ 0 < accountFees.length && (
				<AccountFees accountFees={ accountFees } />
			) }
		</AccountStatusCard>
	);
};

const AccountStatus = ( props ) => {
	const { accountStatus } = props;
	return accountStatus.error ? (
		<AccountStatusError />
	) : (
		<AccountStatusDetails { ...props } />
	);
};

export default AccountStatus;
