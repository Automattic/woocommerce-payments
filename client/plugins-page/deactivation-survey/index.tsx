/**
 * External dependencies
 */
import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { Modal } from 'wcpay/components/wp-components-wrapped/components/modal';

/**
 * Internal dependencies
 */
import './style.scss';
import StripeSpinner from 'wcpay/components/stripe-spinner';
import WooPaymentsIcon from 'assets/images/woopayments.svg?asset';

interface PluginDisableSurveyProps {
	/**
	 * Callback to close the modal.
	 */
	onRequestClose: () => void;
}
const PluginDisableSurvey = ( {
	onRequestClose,
}: PluginDisableSurveyProps ) => {
	const [ isLoading, setIsLoading ] = useState( true );
	const [ iframeHeight, setIframeHeight ] = useState( 600 ); // Default height.

	// Listen for messages from the iframe to set height on load/reload.
	useEffect( () => {
		const handleMessage = ( event: MessageEvent ) => {
			// Verify the origin for security.
			if ( event.origin !== 'https://automattic.survey.fm' ) {
				return;
			}

			// Set height whenever iframe sends embed-size message (load/reload).
			if ( event.data.type === 'embed-size' && event.data.height ) {
				const newHeight = Math.max( event.data.height, 600 ); // Minimum height is 600px.
				setIframeHeight( newHeight );
			}
		};

		window.addEventListener( 'message', handleMessage );

		return () => {
			window.removeEventListener( 'message', handleMessage );
		};
	}, [] );

	return (
		<Modal
			// @ts-expect-error - The Modal component expects a string but we're intentionally using a React element.
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
			{ isLoading && (
				<div className="woopayments-disable-survey-loader">
					<StripeSpinner />
				</div>
			) }
			<iframe
				title={ __(
					'WooPayments Disable Survey',
					'woocommerce-payments'
				) }
				src="https://automattic.survey.fm/woopayments-exit-feedback"
				className="woopayments-disable-survey-iframe"
				style={ {
					height: `${ iframeHeight }px`,
					opacity: isLoading ? 0 : 1,
				} }
				onLoad={ () => {
					setIsLoading( false );
				} }
			/>
		</Modal>
	);
};

export default PluginDisableSurvey;
