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
import {
	formatCurrency,
	formatExplicitCurrency,
} from 'multi-currency/interface/functions';
import type * as AccountOverview from 'wcpay/types/account-overview';
import './style.scss';

interface InstantPayoutModalProps {
	instantBalance: AccountOverview.InstantBalance;
	onClose: () => void;
	onSubmit: () => void;
	inProgress: boolean;
}

const InstantPayoutModal: React.FC< InstantPayoutModalProps > = ( {
	instantBalance: { amount, fee, net, fee_percentage: percentage },
	onClose,
	onSubmit,
	inProgress,
} ) => {
	const learnMoreHref =
		'https://woocommerce.com/document/woopayments/payouts/instant-payouts/';
	const feePercentage = `${ percentage }%`;
	const description = createInterpolateElement(
		/* translators: %s: amount representing the fee percentage, <a>: instant payout doc URL */
		sprintf(
			__(
				'Need cash in a hurry? Instant payouts are available within 30 minutes for a nominal %s service fee. <a>Learn more</a>',
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
			title={ __( 'Instant payout', 'woocommerce-payments' ) }
			onRequestClose={ onClose }
			className="wcpay-instant-payout-modal"
		>
			<p>{ description }</p>
			<ul>
				<li className="wcpay-instant-payout-modal__balance">
					{ __(
						'Balance available for instant payout: ',
						'woocommerce-payments'
					) }
					<span>{ formatCurrency( amount ) }</span>
				</li>
				<li className="wcpay-instant-payout-modal__fee">
					{ sprintf(
						/* translators: %s - amount representing the fee percentage */
						__( '%s service fee: ', 'woocommerce-payments' ),
						feePercentage
					) }
					<span>-{ formatCurrency( fee ) }</span>
				</li>
				<li className="wcpay-instant-payout-modal__net">
					{ __( 'Net payout amount: ', 'woocommerce-payments' ) }
					<span>{ formatExplicitCurrency( net ) }</span>
				</li>
			</ul>

			<div className="wcpay-instant-payout-modal__footer">
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
						/* translators: %s: Monetary amount to pay out */
						__( 'Pay out %s now', 'woocommerce-payments' ),
						formatExplicitCurrency( net )
					) }
				</Button>
			</div>
		</Modal>
	);
};

export default InstantPayoutModal;
