/**
 * External dependencies
 */
import React from 'react';
import { Button, Modal } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { formatCurrency, formatExplicitCurrency } from 'utils/currency';
import './style.scss';

interface StandardDepositModalProps {
	availableBalance: AccountOverview.Balance;
	depositDelayDays: number;
	onClose: () => void;
	onSubmit: () => void;
	inProgress: boolean;
}

const StandardDepositModal = ( {
	availableBalance,
	depositDelayDays,
	onClose,
	onSubmit,
	inProgress,
}: StandardDepositModalProps ): JSX.Element => {
	/* translators: %s: amount representing the available monetary balance, %s: number of business days */
	const description = sprintf(
		__(
			'Ready to get paid? Your available balance of %s will be deposited to your bank account within %s business days.',
			'woocommerce-payments'
		),
		formatExplicitCurrency(
			availableBalance.amount,
			availableBalance.currency
		),
		depositDelayDays
	);

	return (
		<Modal
			title={ __( 'Deposit now', 'woocommerce-payments' ) }
			onRequestClose={ onClose }
			className="wcpay-standard-deposit-modal"
		>
			<p>{ description }</p>

			<div className="wcpay-standard-deposit-modal__footer">
				<Button
					isPrimary
					onClick={ onSubmit }
					isBusy={ inProgress }
					disabled={ inProgress }
				>
					{ sprintf(
						/* translators: %s: Monetary amount to deposit */
						__( 'Deposit %s', 'woocommerce-payments' ),
						formatCurrency(
							availableBalance.amount,
							availableBalance.currency
						)
					) }
				</Button>
				<Button isTertiary onClick={ onClose }>
					{ __( 'Cancel', 'woocommerce-payments' ) }
				</Button>
			</div>
		</Modal>
	);
};

export default StandardDepositModal;
