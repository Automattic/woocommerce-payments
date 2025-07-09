/**
 * External dependencies
 */
import React from 'react';

import { Button } from 'wcpay/components/wp-components-wrapped/components/button';
import { Icon } from 'wcpay/components/wp-components-wrapped/components/icon';
import { Modal } from 'wcpay/components/wp-components-wrapped/components/modal';
import { ExternalLink } from 'wcpay/components/wp-components-wrapped/components/external-link';
import UnbundledWpComponentsProvider from 'wcpay/wordpress-components-context/unbundled-wp-components-provider';
import {
	createInterpolateElement,
	useEffect,
	useState,
} from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { removeQueryArgs } from '@wordpress/url';
import { __, sprintf } from '@wordpress/i18n';
import { recordEvent } from '../tracks';

import './style.scss';

const {
	connectUrl,
	pluginScope,
} = window.wcpaySubscriptionProductOnboardingModal;

const FinishSetupButton = () => {
	const [ isFinishingSetup, setIsFinishingSetup ] = useState( false );

	return (
		<Button
			disabled={ isFinishingSetup }
			href={ connectUrl }
			isBusy={ isFinishingSetup }
			isPrimary
			onClick={ () => {
				recordEvent(
					'wcpay_subscriptions_account_not_connected_product_modal_finish_setup'
				);
				setIsFinishingSetup( true );
			} }
		>
			{ __( 'Finish setup', 'woocommerce-payments' ) }
		</Button>
	);
};

const SubscriptionProductOnboardingModalContent = ( {
	onRequestClose = () => {},
} ) => {
	useEffect( () => {
		recordEvent(
			'wcpay_subscriptions_account_not_connected_product_modal_view'
		);
	}, [] );

	return (
		<Modal
			className="wcpay-subscription-product-modal"
			onRequestClose={ () => {
				recordEvent(
					'wcpay_subscriptions_account_not_connected_product_modal_dismiss'
				);
				onRequestClose();
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
				{ sprintf(
					/* translators: %s: WooPayments */
					__(
						'Verify your business details with %s to accept recurring payments for this subscription product.',
						'woocommerce-payments'
					),
					'WooPayments'
				) }
			</p>
			<p className="wcpay-subscription-product-modal__tos">
				{ createInterpolateElement(
					__(
						'By clicking "Finish setup", you agree to the <a>Terms of Service</a>',
						'woocommerce-payments'
					),
					{
						a: <ExternalLink href="https://wordpress.com/tos/" />,
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
		<UnbundledWpComponentsProvider>
			<SubscriptionProductOnboardingModalContent
				onRequestClose={ () => {
					setOpen( false );
				} }
			/>
		</UnbundledWpComponentsProvider>
	);
};

registerPlugin( 'wcpay-subscription-product-onboarding-modal', {
	icon: null,
	render: SubscriptionProductOnboardingModal,
	scope: pluginScope,
} );
