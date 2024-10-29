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
import WooPaymentsIcon from 'assets/images/woopayments.svg?asset';

const PluginDisableSurvey = ( { onRequestClose } ) => {
	const [ isLoading, setIsLoading ] = useState( true );

	return (
		<Modal
			title={
				<img
					src={ WooPaymentsIcon }
					alt={ __( 'WooPayments Logo', 'woocommerce-payments' ) }
					className="woopayments-disable-survey-logo"
				/>
			}
			isDismissible={ true }
			shouldCloseOnClickOutside={ false } // Should be false because of the iframe.
			shouldCloseOnEsc={ true }
			onRequestClose={ onRequestClose }
			className="woopayments-disable-survey"
		>
			<Loadable isLoading={ isLoading }>
				<iframe
					title={ __(
						'WooPayments Disable Survey',
						'woocommerce-payments'
					) }
					src="https://automattic.survey.fm/woopayments-exit-feedback"
					className="woopayments-disable-survey-iframe"
					onLoad={ () => {
						setIsLoading( false );
					} }
				/>
			</Loadable>
		</Modal>
	);
};

export default PluginDisableSurvey;
