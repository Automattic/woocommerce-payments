/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import interpolateComponents from 'interpolate-components';
import { Button } from '@wordpress/components';

/**
 * Internal Dependencies
 */
import PaymentConfirmIllustration from '../components/payment-confirm-illustration';
import ConfirmationModal from '../components/confirmation-modal';
import PaymentMethodsMap from '../payment-methods-map';
import RequirementsMap from '../requirements-map';
import './activation-modal.scss';

const ConfirmPaymentMethodActivationModal = ( {
	paymentMethod,
	requirements,
	onClose,
	onConfirmClose,
} ) => {
	const requirementsToDisplay = requirements.filter( ( requirement ) => {
		return RequirementsMap.hasOwnProperty( requirement );
	} );

	const handleConfirmationClick = () => {
		onConfirmClose();
	};
	return (
		<ConfirmationModal
			title={ sprintf(
				__( 'One more step to enable %s', 'woocommerce_payments' ),
				PaymentMethodsMap[ paymentMethod ].label
			) }
			shouldCloseOnClickOutside={ false }
			onRequestClose={ onClose }
			className={ 'wcpay-payment-method-confirmation-modal' }
			actions={
				<>
					<Button isSecondary onClick={ onClose }>
						{ __( 'Cancel', 'woocommerce-payments' ) }
					</Button>
					<Button isPrimary onClick={ handleConfirmationClick }>
						{ __( 'Continue', 'woocommerce-payments' ) }
					</Button>
				</>
			}
		>
			<PaymentConfirmIllustration
				Icon={ PaymentMethodsMap[ paymentMethod ].Icon }
				hasBorder={ 'card' !== PaymentMethodsMap[ paymentMethod ].id }
			/>
			{ 0 < requirementsToDisplay.length ? (
				<>
					<p>
						{ sprintf(
							__(
								'You need to provide more information to enable %s on your checkout:',
								'woocommerce-payments'
							),
							PaymentMethodsMap[ paymentMethod ].label
						) }
					</p>
					<ul className={ 'payment-method-requirements-list' }>
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
							'You need to provide more information to enable %s on your checkout.',
							'woocommerce-payments'
						),
						PaymentMethodsMap[ paymentMethod ].label
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
