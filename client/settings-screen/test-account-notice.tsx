/**
 * External dependencies
 */
import React, { useState } from 'react';
import { Button, ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import SetupLivePaymentsModal from 'wcpay/components/sandbox-mode-switch-to-live-notice/modal';
import { recordEvent } from 'wcpay/tracks';
import InlineNotice from 'wcpay/components/inline-notice';

/** Tells a test account's owner, above the settings, how to start taking real payments. */
const TestAccountNotice = () => {
	const [ isModalOpen, setModalOpen ] = useState( false );
	return (
		<InlineNotice
			status="info"
			isDismissible={ false }
			icon
			className="wcpay-settings-screen__test-account-notice"
		>
			<strong>
				{ __( 'You’re using a test account', 'woocommerce-payments' ) }
			</strong>
			<p>
				{ __(
					'Provide additional details about your business so you can begin accepting real payments.',
					'woocommerce-payments'
				) }
			</p>
			<Button
				variant="primary"
				size="compact"
				onClick={ () => {
					recordEvent( 'wcpay_settings_setup_live_payments_click', {
						source: 'wcadmin-settings-screen',
					} );
					setModalOpen( true );
				} }
			>
				{ __( 'Activate payments', 'woocommerce-payments' ) }
			</Button>
			<ExternalLink href="https://woocommerce.com/document/woopayments/testing-and-troubleshooting/test-accounts/">
				{ __( 'Learn more', 'woocommerce-payments' ) }
			</ExternalLink>
			{ isModalOpen && (
				<SetupLivePaymentsModal
					from="WCPAY_SETTINGS_SCREEN"
					source="wcadmin-settings-screen"
					onClose={ () => setModalOpen( false ) }
				/>
			) }
		</InlineNotice>
	);
};

export default TestAccountNotice;
