/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement, render, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';

import { recordEvent } from '../tracks';
import UnconnectedImage from 'assets/images/subscriptions-empty-state-unconnected.svg?asset';

import './style.scss';

const {
	wcpay: { connectUrl, isConnected, newProductUrl },
} = window;

const Image = () => <img src={ UnconnectedImage } alt="" />;

const Description = () => (
	<p className="woo_subscriptions_empty_state__description">
		{ sprintf(
			/* translators: %s: WooPayments */
			__(
				'Track recurring revenue and manage active subscriptions directly from your store’s ' +
					'dashboard — powered by %s.',
				'woocommerce-payments'
			),
			'WooPayments'
		) }
	</p>
);

const TOS = () => (
	<p className="wcpay-empty-subscriptions__tos">
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
);

const ActionButtons = () => {
	const [ isFinishingSetup, setIsFinishingSetup ] = useState( false );
	const [ isCreatingProduct, setIsCreatingProduct ] = useState( false );

	return (
		<div className="woo_subscriptions_empty_state__button_container">
			<Button
				disabled={ isFinishingSetup }
				href={ connectUrl }
				isBusy={ isFinishingSetup }
				isPrimary
				onClick={ () => {
					recordEvent(
						'wcpay_subscriptions_empty_state_finish_setup'
					);
					setIsFinishingSetup( true );
				} }
			>
				{ __( 'Finish setup', 'woocommerce-payments' ) }
			</Button>
			<Button
				disabled={ isCreatingProduct }
				href={ newProductUrl }
				isBusy={ isCreatingProduct }
				isSecondary
				onClick={ () => {
					recordEvent(
						'wcpay_subscriptions_empty_state_create_product'
					);
					setIsCreatingProduct( true );
				} }
			>
				{ __( 'Create subscription product', 'woocommerce-payments' ) }
			</Button>
		</div>
	);
};

const EmptyState = () => {
	return (
		<div className="woo_subscriptions_empty_state__container">
			<Image />
			<Description />
			<TOS />
			<ActionButtons />
		</div>
	);
};

const emptyStateContainer = document.querySelector(
	'#woo_subscriptions_empty_state'
);

if ( emptyStateContainer ) {
	recordEvent( 'wcpay_subscriptions_empty_state_view', {
		is_connected: isConnected ? 'yes' : 'no',
	} );

	if ( ! isConnected ) {
		render( <EmptyState />, emptyStateContainer );
	}
}
