/**
 * External dependencies
 */
import React from 'react';

import { Button, Icon, Modal } from '@wordpress/components';
import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';
import { useState } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { __ } from '@wordpress/i18n';

import './style.scss';

const { connectUrl } = window.wcpaySubscriptionsProductAdmin;

const FinishSetupButton = () => {
	const [ isFinishingSetup, setIsFinishingSetup ] = useState( false );

	return (
		<Button
			disabled={ isFinishingSetup }
			href={ connectUrl }
			isBusy={ isFinishingSetup }
			isPrimary
			onClick={ () => setIsFinishingSetup( true ) }
		>
			{ __( 'Finish setup', 'woocommerce-payments' ) }
		</Button>
	);
};

// TODO: a11y.
const SubscriptionProductSavedAsDraftModal = () => {
	const [ isOpen, setOpen ] = useState( true );

	if ( ! isOpen ) {
		return null;
	}

	return (
		<Modal
			className="wcpay-subscription-product-modal"
			onRequestClose={ () => setOpen( false ) }
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

registerPlugin( 'wcpay-subscriptions-product-admin', {
	icon: null,
	render: SubscriptionProductSavedAsDraftModal,
	scope: 'woocommerce',
} );
