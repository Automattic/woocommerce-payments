/** @format **/

/**
 * External dependencies
 */
import { Button, Modal } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';
import { formatCurrency } from 'utils/currency';

/**
 * Internal dependencies
 */
import './style.scss';

const InstantDepositModal = ( {
	amount,
	fee,
	net,
	onClose,
	onSubmit,
	inProgress,
} ) => {
	const learnMoreHref = 'https://docs.woocommerce.com/document/payments/';
	const description = createInterpolateElement(
		/* translators: <a> - instant payout doc URL */
		__(
			'Need cash in a hurry? Instant deposits are available within 30 minutes for a nominal 1% service fee. <a>Learn more</a>',
			'woocommerce-payments'
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
					{ __( '1% service fee: ', 'woocommerce-payments' ) }
					<span>-{ formatCurrency( fee ) }</span>
				</li>
				<li className="wcpay-instant-deposits-modal__net">
					{ __( 'Net deposit amount: ', 'woocommerce-payments' ) }
					<span>{ formatCurrency( net ) }</span>
				</li>
			</ul>

			<Button
				isPrimary
				onClick={ onSubmit }
				isBusy={ inProgress }
				disabled={ inProgress }
			>
				{ sprintf(
					/* translators: %s: Monetary amount to deposit */
					__( 'Deposit %s now', 'woocommerce-payments' ),
					formatCurrency( net )
				) }
			</Button>
			<Button isSecondary onClick={ onClose }>
				{ __( 'Close', 'woocommerce-payments' ) }
			</Button>
		</Modal>
	);
};

export default InstantDepositModal;
