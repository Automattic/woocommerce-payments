/** @format **/

/**
 * External dependencies
 */
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './style.scss';
import './shared.scss';
import AccountFees from './account-fees';
import AccountStatusItem from './account-status-item';
import DepositsStatus from 'components/deposits-status';
import PaymentsStatus from 'components/payments-status';
import StatusChip from './status-chip';

const AccountStatus = ( props ) => {
	const { accountStatus, accountFees } = props;
	let cardHeader;
	let cardBody;

	if ( accountStatus.error ) {
		cardHeader = (
			<div className="title">
				{ __( 'Account details', 'woocommerce-payments' ) }
			</div>
		);

		cardBody = (
			<div>
				{ __(
					'Error determining the connection status.',
					'woocommerce-payments'
				) }
			</div>
		);
	} else {
		cardHeader = (
			<>
				<div className="title">
					{ __( 'Account details', 'woocommerce-payments' ) }
				</div>
				<div>
					<StatusChip accountStatus={ accountStatus.status } />
				</div>
				<div className="woocommerce-account-status-header-controls">
					<Button disabled isLink>
						{ __( 'Edit details', 'woocommerce-payments' ) }
					</Button>
				</div>
			</>
		);

		cardBody = (
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
							paymentsEnabled={ accountStatus.paymentsEnabled }
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
		);
	}

	return (
		<Card isSmall>
			<CardHeader
				className={
					'woocommerce-card__header woocommerce-account-status-header'
				}
			>
				{ cardHeader }
			</CardHeader>
			<CardBody>{ cardBody }</CardBody>
		</Card>
	);
};

export default AccountStatus;
