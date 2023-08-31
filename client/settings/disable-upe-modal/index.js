/**
 * External dependencies
 */
import React, { useContext, useEffect } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { dispatch } from '@wordpress/data';
import { Button, ExternalLink } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import './style.scss';
import ConfirmationModal from 'components/confirmation-modal';
import useIsUpeEnabled from 'settings/wcpay-upe-toggle/hook';
import WcPayUpeContext from 'settings/wcpay-upe-toggle/context';
import InlineNotice from 'components/inline-notice';
import { useEnabledPaymentMethodIds } from '../../data';
import PaymentMethodIcon from '../payment-method-icon';

const NeedHelpBarSection = () => {
	return (
		<InlineNotice icon status="info" isDismissible={ false }>
			{ interpolateComponents( {
				mixedString: __(
					'Need help? Visit {{ docsLink /}} or {{supportLink /}}.',
					'woocommerce-payments'
				),
				components: {
					docsLink: (
						// eslint-disable-next-line max-len
						<ExternalLink href="https://woocommerce.com/document/woocommerce-payments/payment-methods/additional-payment-methods/">
							{ sprintf(
								/* translators: %s: WooPayments */
								__( '%s docs', 'woocommerce-payments' ),
								'WooPayments'
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

const DisableUpeModalBody = () => {
	const [ enabledPaymentMethodIds ] = useEnabledPaymentMethodIds();
	const upePaymentMethods = enabledPaymentMethodIds.filter(
		( method ) => method !== 'card'
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
			{ upePaymentMethods.length > 0 ? (
				<>
					<p>
						{ __(
							'Payment methods that require the new payments experience:',
							'woocommerce-payments'
						) }
					</p>
					<ul className="deactivating-payment-methods-list">
						{ upePaymentMethods.map( ( method ) => (
							<li key={ method }>
								<PaymentMethodIcon name={ method } showName />
							</li>
						) ) }
					</ul>
				</>
			) : null }
			<NeedHelpBarSection />
		</>
	);
};

const DisableUpeModal = ( { setOpenModal, triggerAfterDisable } ) => {
	const [ isUpeEnabled, setIsUpeEnabled ] = useIsUpeEnabled();
	const { status } = useContext( WcPayUpeContext );

	useEffect( () => {
		if ( ! isUpeEnabled ) {
			setOpenModal( '' );
			triggerAfterDisable();
		}
	}, [ isUpeEnabled, setOpenModal, triggerAfterDisable ] );

	useEffect( () => {
		if ( status === 'error' ) {
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
							disabled={ status === 'pending' }
							onClick={ () => setOpenModal( '' ) }
						>
							{ __( 'Cancel', 'woocommerce-payments' ) }
						</Button>
						<Button
							isPrimary
							isDestructive
							isBusy={ status === 'pending' }
							disabled={ status === 'pending' }
							onClick={ () => setIsUpeEnabled( false ) }
						>
							{ __( 'Disable', 'woocommerce-payments' ) }
						</Button>
					</>
				}
			>
				<DisableUpeModalBody />
			</ConfirmationModal>
		</>
	);
};
export default DisableUpeModal;
