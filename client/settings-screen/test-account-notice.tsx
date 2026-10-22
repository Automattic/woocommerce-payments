/**
 * External dependencies
 */
import React, { useState } from 'react';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import SetupLivePaymentsModal from 'wcpay/components/sandbox-mode-switch-to-live-notice/modal';
import { recordEvent } from 'wcpay/tracks';

/** Explains why test mode is locked on for a test account, with a way to activate payments. */
const TestAccountNotice = () => {
	const [ isModalOpen, setModalOpen ] = useState( false );
	return (
		<p className="wcpay-settings-screen__card-description">
			{ __(
				'You’re using a test account. Activate payments to start taking real payments.',
				'woocommerce-payments'
			) }{ ' ' }
			<Button
				variant="link"
				onClick={ () => {
					recordEvent( 'wcpay_settings_setup_live_payments_click', {
						source: 'wcadmin-settings-screen',
					} );
					setModalOpen( true );
				} }
			>
				{ __( 'Activate payments', 'woocommerce-payments' ) }
			</Button>
			{ isModalOpen && (
				<SetupLivePaymentsModal
					from="WCPAY_SETTINGS_SCREEN"
					source="wcadmin-settings-screen"
					onClose={ () => setModalOpen( false ) }
				/>
			) }
		</p>
	);
};

export default TestAccountNotice;
