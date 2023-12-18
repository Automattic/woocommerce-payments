/**
 * External dependencies
 */
import React from 'react';
import { Button, ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import ConfirmationModal from '../../components/confirmation-modal';

function DevModeConfirmationModal( { onClose, onConfirm } ) {
	return (
		<ConfirmationModal
			title={ __( 'Enable test mode', 'woocommerce-payments' ) }
			onRequestClose={ onClose }
			actions={
				<>
					<Button onClick={ onClose } isSecondary>
						Cancel
					</Button>
					<Button onClick={ onConfirm } isPrimary>
						Enable
					</Button>
				</>
			}
		>
			<h3>
				{ __(
					'Are you sure you want to enable test mode?',
					'woocommerce-payments'
				) }
			</h3>
			<p>
				{ __(
					"Test mode lets you try out payments, refunds, disputes and other such processes as you're working on your store " +
						'without handling live payment information. ' +
						'All incoming orders will be simulated, and test mode will have to be disabled before you can accept real orders.',
					'woocommerce-payments'
				) }
			</p>
			<ExternalLink
				// eslint-disable-next-line max-len
				href="https://woo.com/document/woopayments/testing-and-troubleshooting/testing/"
			>
				{ __( 'Learn more about test mode', 'woocommerce-payments' ) }
			</ExternalLink>
		</ConfirmationModal>
	);
}

export default DevModeConfirmationModal;
