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
					/** translators: %s is the capability label. */
					__(
						'Choosing to continue will remove the option to accept %s cards from your customers. ' +
							'The option to enable %s will not appear again.',
						'woocommerce-payments'
					),
					label,
					label
				) }
			</p>
		</ConfirmationModal>
	);
};
export default DismissConfirmationModal;
