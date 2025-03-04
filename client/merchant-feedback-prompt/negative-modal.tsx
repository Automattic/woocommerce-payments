/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Modal } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import Loadable from 'wcpay/components/loadable';

interface NegativeFeedbackModalProps {
	onRequestClose: () => void;
}

export const NegativeFeedbackModal: React.FC< NegativeFeedbackModalProps > = ( {
	onRequestClose,
} ) => {
	const [ isLoading, setIsLoading ] = useState( true );

	return (
		<Modal
			title={ __( 'Share your feedback', 'woocommerce-payments' ) }
			className="wcpay-merchant-feedback-modal"
			isDismissible={ true }
			shouldCloseOnClickOutside={ false } // Should be false because of the iframe.
			shouldCloseOnEsc={ true }
			onRequestClose={ onRequestClose }
		>
			<Loadable isLoading={ isLoading }>
				<iframe
					title={ __(
						'WooPayments Disable Survey',
						'woocommerce-payments'
					) }
					src="https://automattic.survey.fm/woopayments-feedback-campaign-h1-2025"
					className="wcpay-merchant-feedback-modal__iframe"
					onLoad={ () => {
						setIsLoading( false );
					} }
				/>
			</Loadable>
		</Modal>
	);
};
