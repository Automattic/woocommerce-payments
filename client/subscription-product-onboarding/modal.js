/**
 * External dependencies
 */
import React from 'react';

import { Button, Icon, Modal } from '@wordpress/components';
import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';
import { useEffect, useState } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { removeQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';
import wcpayTracks from '../tracks';

import './style.scss';

const { connectUrl } = window.wcpaySubscriptionProductOnboardingModal;

const FinishSetupButton = () => {
	const [ isFinishingSetup, setIsFinishingSetup ] = useState( false );

	return (
		<Button
			disabled={ isFinishingSetup }
			href={ connectUrl }
			isBusy={ isFinishingSetup }
			isPrimary
			onClick={ () => {
				wcpayTracks.recordEvent(
					wcpayTracks.events
						.SUBSCRIPTIONS_ACCOUNT_NOT_CONNECTED_PRODUCT_MODAL_FINISH_SETUP
				);
				setIsFinishingSetup( true );
			} }
		>
			{ __( 'Finish setup', 'woocommerce-payments' ) }
		</Button>
	);
};

const SubscriptionProductOnboardingModal = () => {
	const [ isOpen, setOpen ] = useState( true );

	useEffect( () => {
		if ( window?.history ) {
			window.history.replaceState(
				null,
				null,
				removeQueryArgs(
					window.location.href,
					'wcpay-subscription-saved-as-draft'
				)
			);
		}
	}, [] );

	if ( ! isOpen ) {
		return null;
	}

	return (
		<Modal
			className="wcpay-subscription-product-modal"
			onRequestClose={ () => {
				wcpayTracks.recordEvent(
					wcpayTracks.events
						.SUBSCRIPTIONS_ACCOUNT_NOT_CONNECTED_PRODUCT_MODAL_DISMISS
				);
				setOpen( false );
			} }
			shouldCloseOnClickOutside={ false }
		>
			<p className="wcpay-subscription-product-modal__title">
				{ __(
					'One more step to accept recurring payments',
					'woocommerce-payments'
				) }
			</p>
			<p>
				{ __(
					'Verify your business details with WooCommerce Payments to accept recurring payments for this subscription product.',
					'woocommerce-payments'
				) }
			</p>
			<p className="wcpay-subscription-product-modal__tos">
				{ createInterpolateElement(
					__(
						'By clicking "Finish setup", you agree to the <a>Terms of Service</a>',
						'woocommerce-payments'
					),
					{
						a: (
							// eslint-disable-next-line jsx-a11y/anchor-has-content
							<a
								href="https://wordpress.com/tos/"
								target="_blank"
								rel="noreferrer"
							/>
						),
					}
				) }
			</p>
			<div className="wcpay-subscription-product-modal__footer">
				<div className="wcpay-subscription-product-modal__saved-indicator">
					<Icon icon="saved" />
					<p>
						{ __(
							'We’ve saved your product as a draft.',
							'woocommerce-payments'
						) }
					</p>
				</div>
				<FinishSetupButton />
			</div>
		</Modal>
	);
};

registerPlugin( 'wcpay-subscription-product-onboarding-modal', {
	icon: null,
	render: SubscriptionProductOnboardingModal,
	scope: 'woocommerce',
} );
