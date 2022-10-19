/**
 * External dependencies
 */
import React from 'react';
import { Button, ExternalLink, Modal } from '@wordpress/components';
import { createInterpolateElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { formatExplicitCurrency } from 'utils/currency';
import './style.scss';

type StandardDepositModalProps = {
	availableBalance: AccountOverview.Balance;
	onClose: () => void;
	onSubmit: () => void;
	inProgress: boolean;
};

const StandardDepositModal: React.FC< StandardDepositModalProps > = ( {
	availableBalance,
	onClose,
	onSubmit,
	inProgress,
} ) => {
	/* translators: %s: amount representing the available monetary balance */
	const description = createInterpolateElement(
		sprintf(
			__(
				'Your full available balance of <strong>%s</strong> will be deposited within 1-2 business days, and the amount received may differ if your available balance changes within the processing time. <a>Learn more</a>',
				'woocommerce-payments'
			),
			formatExplicitCurrency(
				availableBalance.amount,
				availableBalance.currency
			)
		),
		{
			strong: <strong />,
			a: (
				<ExternalLink href="https://woocommerce.com/document/payments/#section-5" />
			),
		}
	);

	return (
		<Modal
			title={ __( 'Deposit funds', 'woocommerce-payments' ) }
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
					{ __( 'Submit deposit', 'woocommerce-payments' ) }
				</Button>
				<Button isLink onClick={ onClose }>
					{ __( 'Cancel', 'woocommerce-payments' ) }
				</Button>
			</div>
		</Modal>
	);
};

export default StandardDepositModal;
