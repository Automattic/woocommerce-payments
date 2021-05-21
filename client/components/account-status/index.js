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
	const { title, content, children, value } = props;
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
			<CardBody>{ children || value || content }</CardBody>
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
			<FlexItem>
				{ __( 'Account details', 'woocommerce-payments' ) }
			</FlexItem>
			<FlexBlock>
				<StatusChip accountStatus={ accountStatus.status } />
			</FlexBlock>
			<FlexItem className={ 'woocommerce-account-status__controls' }>
				<Button disabled isLink>
					{ __( 'Edit details', 'woocommerce-payments' ) }
				</Button>
			</FlexItem>
		</>
	);

	return (
		<AccountStatusCard title={ cardTitle }>
			{ accountStatus.email && (
				<AccountStatusItem
					label={ __( 'Connected email:', 'woocommerce-payments' ) }
				>
					{ accountStatus.email }
				</AccountStatusItem>
			) }
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
			<AccountStatusItem
				align={ 'flex-start' }
				label={ __( 'BaseFee:', 'woocommerce-payments' ) }
			>
				<AccountFees accountFees={ accountFees } />
			</AccountStatusItem>
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
