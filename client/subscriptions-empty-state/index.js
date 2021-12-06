/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { render, useEffect, useState } from '@wordpress/element';

import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';
import { Button } from '@wordpress/components';

import wcpayTracks from '../tracks';

import connectedImage from '../../assets/images/subscriptions-empty-state-connected.svg';
import unconnectedImage from '../../assets/images/subscriptions-empty-state-unconnected.svg';

import './style.scss';

const {
	wcpay: { connectUrl, isConnected, newProductUrl },
} = window;

const Image = () => (
	<img src={ isConnected ? connectedImage : unconnectedImage } alt="" />
);

const Description = () => (
	<p className="wcpay-empty-subscriptions__description">
		{ isConnected
			? __(
					'This is where you’ll see and manage all subscriptions in your store. Create a ' +
						'subscription product to turn one-time purchases into a steady income.',
					'woocommerce-payments'
			  )
			: __(
					'Track recurring revenue and manage active subscriptions directly from your store’s ' +
						'dashboard — powered by WooCommerce Payments.',
					'woocommerce-payments'
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
		<div className="wcpay-empty-subscriptions__button_container">
			{ ! isConnected && (
				<Button
					disabled={ isFinishingSetup }
					href={ connectUrl }
					isBusy={ isFinishingSetup }
					isPrimary
					onClick={ () => {
						wcpayTracks.recordEvent(
							wcpayTracks.events
								.SUBSCRIPTIONS_EMPTY_STATE_FINISH_SETUP
						);
						setIsFinishingSetup( true );
					} }
				>
					{ __( 'Finish setup', 'woocommerce-payments' ) }
				</Button>
			) }
			<Button
				disabled={ isCreatingProduct }
				href={ newProductUrl }
				isBusy={ isCreatingProduct }
				isSecondary
				onClick={ () => {
					wcpayTracks.recordEvent(
						wcpayTracks.events
							.SUBSCRIPTIONS_EMPTY_STATE_CREATE_PRODUCT
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
	useEffect( () => {
		wcpayTracks.recordEvent(
			wcpayTracks.events.SUBSCRIPTIONS_EMPTY_STATE_VIEW,
			{
				is_connected: isConnected ? 'yes' : 'no',
			}
		);
	}, [] );

	return (
		<div className="wcpay-empty-subscriptions__container">
			<Image />
			<Description />
			{ ! isConnected && <TOS /> }
			<ActionButtons />
		</div>
	);
};

render(
	<EmptyState />,
	document.querySelector( '#wcpay_subscriptions_empty_state' )
);
