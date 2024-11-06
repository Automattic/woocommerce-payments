/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { Button, Modal } from '@wordpress/components';
import { Icon, currencyDollar } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import BlockEmbedIcon from 'components/icons/block-embed';
import BlockPostAuthorIcon from 'components/icons/block-post-author';
import './style.scss';
import { recordEvent } from 'wcpay/tracks';

interface Props {
	from: string;
	source: string;
	onClose: () => void;
}

const SetupLivePaymentsModal: React.FC< Props > = ( {
	from,
	source,
	onClose,
}: Props ) => {
	const [ isSubmitted, setSubmitted ] = useState( false );
	const handleSetup = () => {
		setSubmitted( true );

		recordEvent( 'wcpay_onboarding_flow_setup_live_payments', {
			from,
			source,
		} );

		window.location.href = addQueryArgs( wcpaySettings.connectUrl, {
			'wcpay-disable-onboarding-test-mode': 'true',
			from,
			source: 'wcpay-setup-live-payments', // Overwrite any existing source because we are starting over.
		} );
	};

	const trackAndClose = () => {
		setSubmitted( false );

		recordEvent( 'wcpay_setup_live_payments_modal_exit', {
			from,
			source,
		} );

		onClose();
	};

	return (
		<Modal
			title={ __(
				'Set up live payments on your store',
				'woocommerce-payments'
			) }
			className="wcpay-setup-real-payments-modal"
			isDismissible={ true }
			onRequestClose={ trackAndClose }
		>
			<p className="wcpay-setup-real-payments-modal__headline">
				{ __(
					'Before proceeding, please take note of the following information:',
					'woocommerce-payments'
				) }
			</p>
			<div className="wcpay-setup-real-payments-modal__content">
				<Icon icon={ BlockEmbedIcon } />
				{ __(
					'Your test account will be deactivated and your transaction records will be preserved for future reference.',
					'woocommerce-payments'
				) }
				<Icon icon={ BlockPostAuthorIcon } />
				{ __(
					'The owner, business and contact information will be required.',
					'woocommerce-payments'
				) }
				<Icon icon={ currencyDollar } />
				{ __(
					'We will need your banking details in order to process any deposits to you.',
					'woocommerce-payments'
				) }
			</div>
			<div className="wcpay-setup-real-payments-modal__footer">
				<Button variant="tertiary" onClick={ trackAndClose }>
					{ __( 'Cancel', 'woocommerce-payments' ) }
				</Button>
				<Button
					variant="primary"
					isBusy={ isSubmitted }
					disabled={ isSubmitted }
					onClick={ handleSetup }
				>
					{ __( 'Continue setup', 'woocommerce-payments' ) }
				</Button>
			</div>
		</Modal>
	);
};

export default SetupLivePaymentsModal;
