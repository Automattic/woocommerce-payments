import React, { useContext, useEffect } from 'react';

import { __ } from '@wordpress/i18n';
import { dispatch } from '@wordpress/data';
import { Button, ExternalLink } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import './style.scss';
import ConfirmationModal from 'components/confirmation-modal';
import PaymentMethod from 'components/payment-methods-list/payment-method';
import useIsUpeEnabled from 'settings/wcpay-upe-toggle/hook';
import WcPayUpeContext from 'settings/wcpay-upe-toggle/context';
import InlineNotice from '../../components/inline-notice';

const NeedHelpBarSection = () => {
	return (
		<InlineNotice status="info" isDismissible={ false }>
			{ interpolateComponents( {
				mixedString: __(
					'Need help? Visit {{ docsLink /}} or {{supportLink /}}.',
					'woocommerce-payments'
				),
				components: {
					docsLink: (
						// eslint-disable-next-line max-len
						<ExternalLink href="https://docs.woocommerce.com/document/payments/additional-payment-methods/#introduction">
							{ __(
								'WooCommerce Payments docs',
								'woocommerce-payments'
							) }
						</ExternalLink>
					),
					supportLink: (
						// eslint-disable-next-line max-len
						<ExternalLink href="https://woocommerce.com/contact-us/">
							{ __( 'contact support', 'woocommerce-payments' ) }
						</ExternalLink>
					),
				},
			} ) }
		</InlineNotice>
	);
};

const DisableUpeModalBody = ( { enabledMethods } ) => {
	const upePaymentMethods = enabledMethods.filter(
		( method ) => 'card' !== method.id
	);

	return (
		<>
			<p>
				{ __(
					// eslint-disable-next-line max-len
					'Without the new payments experience, your customers will only be able to pay using credit card / debit card. You will not be able to add other sales-boosting payment methods anymore.',
					'woocommerce-payments'
				) }
			</p>
			{ 0 < upePaymentMethods.length ? (
				<>
					<p>
						{ __(
							'Payment methods that require the new payments experience:',
							'woocommerce-payments'
						) }
					</p>
					<ul>
						{ upePaymentMethods.map( ( { id, label, Icon } ) => (
							<PaymentMethod
								key={ id }
								Icon={ Icon }
								label={ label }
							/>
						) ) }
					</ul>
				</>
			) : null }
			<NeedHelpBarSection />
		</>
	);
};

const DisableUpeModal = ( {
	enabledMethods,
	setOpenModal,
	triggerAfterDisable,
} ) => {
	const [ isUpeEnabled, setIsUpeEnabled ] = useIsUpeEnabled();
	const { status } = useContext( WcPayUpeContext );

	useEffect( () => {
		if ( ! isUpeEnabled ) {
			setOpenModal( '' );
			triggerAfterDisable();
		}
	}, [ isUpeEnabled, setOpenModal, triggerAfterDisable ] );

	useEffect( () => {
		if ( 'error' === status ) {
			dispatch( 'core/notices' ).createErrorNotice(
				__(
					'There was an error disabling the new payment methods.',
					'woocommerce-payments'
				)
			);
		}
	}, [ status ] );

	return (
		<>
			<ConfirmationModal
				className="disable-modal-section"
				title={ __(
					'Disable the new payments experience',
					'woocommerce-payments'
				) }
				onRequestClose={ () => setOpenModal( '' ) }
				actions={
					<>
						<Button
							isSecondary
							disabled={ 'pending' === status }
							onClick={ () => setOpenModal( '' ) }
						>
							{ __( 'Cancel', 'woocommerce-payments' ) }
						</Button>
						<Button
							isPrimary
							isDestructive
							isBusy={ 'pending' === status }
							disabled={ 'pending' === status }
							onClick={ () => setIsUpeEnabled( false ) }
						>
							{ __( 'Disable', 'woocommerce-payments' ) }
						</Button>
					</>
				}
			>
				<DisableUpeModalBody enabledMethods={ enabledMethods } />
			</ConfirmationModal>
		</>
	);
};
export default DisableUpeModal;
