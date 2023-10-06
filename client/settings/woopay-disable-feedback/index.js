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

const WooPayDisableFeedback = ( { onRequestClose } ) => {
	const [ isLoading, setIsLoading ] = useState( true );

	return (
		<Modal
			title={ __( 'WooPay Feedback', 'woocommerce-payments' ) }
			isDismissible={ true }
			shouldCloseOnEsc={ true }
			onRequestClose={ onRequestClose }
			className="woopay-disable-feedback"
		>
			<Loadable isLoading={ isLoading }>
				<iframe
					title={ __(
						'WooPay Disable Feedback',
						'woocommerce-payments'
					) }
					src="https://woocommerce.survey.fm/woopay-disabled-merchants-feedback-triggered"
					className="woopay-disable-feedback-iframe"
					onLoad={ () => {
						setIsLoading( false );
					} }
				/>
			</Loadable>
		</Modal>
	);
};

export default WooPayDisableFeedback;
