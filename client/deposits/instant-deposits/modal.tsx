/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { Button, Modal } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { formatCurrency, formatExplicitCurrency } from 'utils/currency';
import type * as AccountOverview from 'wcpay/types/account-overview';
import './style.scss';

interface InstantDepositModalProps {
	instantBalance: AccountOverview.InstantBalance;
	onClose: () => void;
	onSubmit: () => void;
	inProgress: boolean;
}

const InstantDepositModal: React.FC< InstantDepositModalProps > = ( {
	instantBalance: { amount, fee, net, fee_percentage: percentage },
	onClose,
	onSubmit,
	inProgress,
} ) => {
	const learnMoreHref =
		'https://woocommerce.com/document/woocommerce-payments/deposits/instant-deposits/';
	const feePercentage = `${ percentage }%`;
	const description = createInterpolateElement(
		/* translators: %s: amount representing the fee percentage, <a>: instant payout doc URL */
		sprintf(
			__(
				'Need cash in a hurry? Instant deposits are available within 30 minutes for a nominal %s service fee. <a>Learn more</a>',
				'woocommerce-payments'
			),
			feePercentage
		),
		{
			a: (
				// eslint-disable-next-line jsx-a11y/anchor-has-content
				<a
					href={ learnMoreHref }
					target="_blank"
					rel="noopener noreferrer"
				/>
			),
		}
	);

	return (
		<Modal
			title={ __( 'Instant deposit', 'woocommerce-payments' ) }
			onRequestClose={ onClose }
			className="wcpay-instant-deposits-modal"
		>
			<p>{ description }</p>
			<ul>
				<li className="wcpay-instant-deposits-modal__balance">
					{ __(
						'Balance available for instant deposit: ',
						'woocommerce-payments'
					) }
					<span>{ formatCurrency( amount ) }</span>
				</li>
				<li className="wcpay-instant-deposits-modal__fee">
					{ sprintf(
						/* translators: %s - amount representing the fee percentage */
						__( '%s service fee: ', 'woocommerce-payments' ),
						feePercentage
					) }
					<span>-{ formatCurrency( fee ) }</span>
				</li>
				<li className="wcpay-instant-deposits-modal__net">
					{ __( 'Net deposit amount: ', 'woocommerce-payments' ) }
					<span>{ formatExplicitCurrency( net ) }</span>
				</li>
			</ul>

			<div className="wcpay-instant-deposits-modal__footer">
				<Button isSecondary onClick={ onClose }>
					{ __( 'Cancel', 'woocommerce-payments' ) }
				</Button>
				<Button
					isPrimary
					onClick={ onSubmit }
					isBusy={ inProgress }
					disabled={ inProgress }
				>
					{ sprintf(
						/* translators: %s: Monetary amount to deposit */
						__( 'Deposit %s now', 'woocommerce-payments' ),
						formatExplicitCurrency( net )
					) }
				</Button>
			</div>
		</Modal>
	);
};

export default InstantDepositModal;
