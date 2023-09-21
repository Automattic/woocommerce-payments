/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { DismissConfirmationModalProps } from './types';

/**
 * Internal dependencies
 */
import ConfirmationModal from 'wcpay/components/confirmation-modal';

const DismissConfirmationModal: React.FC< DismissConfirmationModalProps > = ( {
	onClose,
	onSubmit,
	label,
} ): JSX.Element => {
	const buttonContent = (
		<>
			<Button isSecondary onClick={ onClose }>
				{ __( 'Cancel', 'woocommerce-payments' ) }
			</Button>
			<Button isPrimary onClick={ onSubmit }>
				{ __( 'Yes, continue', 'woocommerce-payments' ) }
			</Button>
		</>
	);

	return (
		<ConfirmationModal
			title={ __( 'Remove', 'woocommerce-payments' ) + ' ' + label }
			isDismissible={ false }
			className="dismiss-confirmation-modal"
			onRequestClose={ onClose }
			actions={ buttonContent }
		>
			<p>
				{ sprintf(
					/** translators: %s is the currency code, e.g. USD. */
					__(
						'Please confirme, by chooising "Yes, continue" you will remove the option to add the ' +
							'the %s payment method for your customers. This option will not appear again.',
						'woocommerce-payments'
					),
					label
				) }
			</p>
		</ConfirmationModal>
	);
};
export default DismissConfirmationModal;
