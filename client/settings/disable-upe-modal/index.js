import React, { useContext } from 'react';

import { __ } from '@wordpress/i18n';
import { Button, Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import '../survey-modal/style.scss';
import ConfirmationModal from 'components/confirmation-modal';
import PaymentMethod from 'components/payment-methods-list/payment-method';
import useIsUpeEnabled from 'settings/wcpay-upe-toggle/hook';
import WcPayUpeContext from 'settings/wcpay-upe-toggle/context';

// create the disable modal
// first(red) button disables UPE
// hook up onClick to show modal
// close modal upon successful submission of form(either button click)
// Blue section at the bottom with Need help? text
// write tests

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

const SubmissionErrorNotice = () => {
	const { status } = useContext( WcPayUpeContext );
	return 'error' === status ? (
		<Notice>
			{ __(
				'There was an error disabling the new payment methods.',
				'woocommerce-payments'
			) }
		</Notice>
	) : null;
};

const DisableUPEModal = ( { enabledMethods, setIsModalOpen } ) => {
	return (
		<>
			<SubmissionErrorNotice />
			<ConfirmationModal
				className="survey-section"
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
