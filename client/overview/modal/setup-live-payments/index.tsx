/**
 * External dependencies
 */
import React from 'react';
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

interface Props {
	closeModal: () => void;
}

const SetupLivePaymentsModal: React.FC< Props > = ( { closeModal }: Props ) => {
	const handleSetup = () => {
		window.location.href = addQueryArgs( wcpaySettings.connectUrl, {
			'wcpay-disable-onboarding-test-mode': true,
		} );
	};

	return (
		<Modal
			title={ __(
				'Set up live payments on your store',
				'woocommerce-payments'
			) }
			className="wcpay-setup-real-payments-modal"
			isDismissible={ true }
			onRequestClose={ closeModal }
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
				<Button isTertiary onClick={ closeModal }>
					{ __( 'Cancel', 'woocommerce-payments' ) }
				</Button>
				<Button isPrimary onClick={ handleSetup }>
					{ __( 'Continue setup', 'woocommerce-payments' ) }
				</Button>
			</div>
		</Modal>
	);
};

export default SetupLivePaymentsModal;
