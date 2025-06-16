/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import { Button } from '@wordpress/components';

/**
 * Internal Dependencies
 */
import PaymentConfirmIllustration from 'wcpay/components/payment-confirm-illustration';
import ConfirmationModal from 'wcpay/components/confirmation-modal';
import PaymentMethodsMap from 'wcpay/payment-methods-map';
import RequirementsMap from 'wcpay/requirements-map';
import './activation-modal.scss';

const ConfirmPaymentMethodActivationModal = ( {
	paymentMethod,
	requirements,
	onClose,
	onConfirmClose,
}: {
	paymentMethod: string;
	requirements: string[];
	onClose: () => void;
	onConfirmClose: () => void;
} ): JSX.Element => {
	const requirementsToDisplay = requirements.filter( ( requirement ) => {
		return RequirementsMap.hasOwnProperty( requirement );
	} );

	const paymentMethodInformation = PaymentMethodsMap[ paymentMethod ];

	return (
		<ConfirmationModal
			title={ sprintf(
				// translators: %s is the name of a payment method.
				__( 'One more step to enable %s', 'woocommerce-payments' ),
				paymentMethodInformation.label
			) }
			shouldCloseOnClickOutside={ false }
			onRequestClose={ onClose }
			className="wcpay-payment-method-confirmation-modal"
			actions={
				<>
					<Button isSecondary onClick={ onClose }>
						{ __( 'Cancel', 'woocommerce-payments' ) }
					</Button>
					<Button isPrimary onClick={ onConfirmClose }>
						{ __( 'Continue', 'woocommerce-payments' ) }
					</Button>
				</>
			}
		>
			<PaymentConfirmIllustration
				icon={ paymentMethodInformation.icon }
				hasBorder={ 'card' !== paymentMethodInformation.id }
			/>
			{ 0 < requirementsToDisplay.length ? (
				<>
					<p>
						{ sprintf(
							__(
								// translators: %s is the name of a payment method.
								'You need to provide more information to enable %s on your checkout:',
								'woocommerce-payments'
							),
							paymentMethodInformation.label
						) }
					</p>
					<ul className="payment-method-requirements-list">
						{ requirementsToDisplay.map( ( requirement, index ) => (
							<li key={ 'requirement' + index }>
								{ RequirementsMap[ requirement ] ??
									requirement }
							</li>
						) ) }
					</ul>
				</>
			) : (
				<p>
					{ sprintf(
						__(
							// translators: %s is the name of a payment method.
							'You need to provide more information to enable %s on your checkout.',
							'woocommerce-payments'
						),
						paymentMethodInformation.label
					) }
				</p>
			) }
			<p>
				{ interpolateComponents( {
					mixedString: __(
						'If you choose to continue, our payment partner Stripe will send an e-mail ' +
							'to {{merchantEmail /}} to collect the required information',
						'woocommerce-payments'
					),
					components: {
						merchantEmail: (
							<b>{ wcpaySettings?.accountEmail ?? '' }</b>
						),
					},
				} ) }
			</p>
		</ConfirmationModal>
	);
};

export default ConfirmPaymentMethodActivationModal;
