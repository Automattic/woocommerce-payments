/** @format **/

/**
 * External dependencies
 */
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Flex,
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
	const { title, content } = props;
	return (
		<Card isSmall>
			<CardHeader>{ title }</CardHeader>
			<CardBody>{ content }</CardBody>
		</Card>
	);
};

const AccountStatusError = () => {
	return (
		<AccountStatusCard
			title={ __( 'Account details', 'woocommerce-payments' ) }
			content={ __(
				'Error determining the connection status.',
				'woocommerce-payments'
			) }
		/>
	);
};

const AccountStatusDetails = ( props ) => {
	const { accountStatus, accountFees } = props;

	return (
		<AccountStatusCard
			title={
				<Flex
					direction={ 'row' }
					align={ 'center' }
					justify={ 'left' }
					gap={ 3 }
					expanded
				>
					<FlexItem>
						{ __( 'Account details', 'woocommerce-payments' ) }
					</FlexItem>
					<FlexBlock>
						<StatusChip accountStatus={ accountStatus.status } />
					</FlexBlock>
					<FlexItem
						className={ 'woocommerce-account-status__controls' }
					>
						<Button disabled isLink>
							{ __( 'Edit details', 'woocommerce-payments' ) }
						</Button>
					</FlexItem>
				</Flex>
			}
			content={
				<>
					{ accountStatus.email && (
						<AccountStatusItem
							label={ __(
								'Connected email:',
								'woocommerce-payments'
							) }
							value={ accountStatus.email }
						/>
					) }
					<AccountStatusItem
						label={ __( 'Payments:', 'woocommerce-payments' ) }
						value={
							<PaymentsStatus
								paymentsEnabled={
									accountStatus.paymentsEnabled
								}
							/>
						}
					/>
					<AccountStatusItem
						label={ __( 'Deposits:', 'woocommerce-payments' ) }
						value={
							<DepositsStatus
								depositsStatus={ accountStatus.depositsStatus }
							/>
						}
					/>
					<AccountStatusItem
						label={ __( 'BaseFee:', 'woocommerce-payments' ) }
						value={ <AccountFees accountFees={ accountFees } /> }
					/>
				</>
			}
		/>
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
