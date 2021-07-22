import React, { useContext, useEffect } from 'react';

import { __ } from '@wordpress/i18n';
import { dispatch } from '@wordpress/data';
import { Button, Dashicon } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import ConfirmationModal from 'components/confirmation-modal';
import PaymentMethod from 'components/payment-methods-list/payment-method';
import useIsUpeEnabled from 'settings/wcpay-upe-toggle/hook';
import WcPayUpeContext from 'settings/wcpay-upe-toggle/context';

// @todo: write tests
// @todo: add links to Need help? section below.

const NeedHelpBarSection = () => {
	return (
		<div className="disable-modal-help-notice">
			<Dashicon className="disable-help-icon" icon="info-outline" />
			<p>
				{ __(
					'Need help? Visit WooCommerce Payments docs or contact support.',
					'woocommerce-payments'
				) }
			</p>
		</div>
	);
};

const DisableUPEModalBody = ( { enabledMethods } ) => {
	return (
		<>
			<p>
				{ __(
					// eslint-disable-next-line max-len
					'Without the new payments experience, your customers will no longer be able to pay using the new payment methods listed below.',
					'woocommerce-payments'
				) }
			</p>
			<p>
				{ __(
					'Payment methods that require the new payments experience:',
					'woocommerce-payments'
				) }
			</p>
			<ul>
				{ enabledMethods.map( ( { id, label, Icon } ) => (
					<PaymentMethod key={ id } Icon={ Icon } label={ label } />
				) ) }
			</ul>
			<NeedHelpBarSection />
		</>
	);
};

const DisableSubmitButton = () => {
	const [ , setIsUpeEnabled ] = useIsUpeEnabled();
	const { status } = useContext( WcPayUpeContext );
	return (
		<Button
			isBusy={ 'pending' === status }
			disabled={ 'pending' === status }
			isDestructive
			isPrimary
			onClick={ () => setIsUpeEnabled( false ) }
		>
			{ __( 'Disable', 'woocommerce-payments' ) }
		</Button>
	);
};

const DisableUPEModal = ( {
	enabledMethods,
	setIsModalOpen,
	triggerAfterDisable,
} ) => {
	const [ isUpeEnabled ] = useIsUpeEnabled();
	const { status } = useContext( WcPayUpeContext );

	useEffect( () => {
		if ( ! isUpeEnabled ) {
			setIsModalOpen( false );
			triggerAfterDisable();
		}
	}, [ isUpeEnabled, setIsModalOpen, triggerAfterDisable ] );

	useEffect( () => {
		if ( 'error' === status ) {
			dispatch( 'core/notices' ).createErrorNotice(
				__(
					'There was an error disabling the new payment methods.',
					'woocommerce-payments'
				)
			);
		}
	} );

	return (
		<>
			<ConfirmationModal
				className="disable-modal-section"
				title={ __(
					'Disable the new payments experience',
					'woocommerce-payments'
				) }
				onRequestClose={ () => setIsModalOpen( false ) }
				actions={ <DisableSubmitButton /> }
			>
				<DisableUPEModalBody enabledMethods={ enabledMethods } />
			</ConfirmationModal>
		</>
	);
};
export default DisableUPEModal;
