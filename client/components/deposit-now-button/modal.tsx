/**
 * External dependencies
 */
import React from 'react';
import { Button, Modal } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { formatExplicitCurrency } from 'utils/currency';
import './style.scss';

interface DepositNowModalProps {
	availableBalance: AccountOverview.Balance;
	depositDelayDays: number;
	onClose: () => void;
	onSubmit: () => void;
	inProgress: boolean;
}

const DepositNowModal = ( {
	availableBalance,
	depositDelayDays,
	onClose,
	onSubmit,
	inProgress,
}: DepositNowModalProps ): JSX.Element => {
	const depositAmount = formatExplicitCurrency(
		availableBalance.amount,
		availableBalance.currency
	);
	/* translators: %s: amount representing the available monetary balance, %s: number of business days */
	const description = sprintf(
		__(
			'Ready to get paid? Your available balance of %s will be deposited to your bank account within %s business days.',
			'woocommerce-payments'
		),
		depositAmount,
		depositDelayDays
	);

	return (
		<Modal
			title={ __( 'Deposit Now', 'woocommerce-payments' ) }
			onRequestClose={ onClose }
			className="wcpay-deposit-now-modal"
		>
			<p>{ description }</p>

			<div className="wcpay-deposit-now-modal__footer">
				<Button
					isPrimary
					onClick={ onSubmit }
					isBusy={ inProgress }
					disabled={ inProgress }
				>
					{ sprintf(
						/* translators: %s: Monetary amount to deposit */
						__( 'Deposit %s', 'woocommerce-payments' ),
						depositAmount
					) }
				</Button>
				<Button isTertiary onClick={ onClose }>
					{ __( 'Cancel', 'woocommerce-payments' ) }
				</Button>
			</div>
		</Modal>
	);
};

export default DepositNowModal;
