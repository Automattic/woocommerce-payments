/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
	createInterpolateElement,
	render,
	useEffect,
	useState,
} from '@wordpress/element';
import { Button } from '@wordpress/components';

import wcpayTracks from '../tracks';
import UnconnectedImage from 'assets/images/subscriptions-empty-state-unconnected.svg?asset';

import './style.scss';

const {
	wcpay: { connectUrl, newProductUrl },
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
					wcpayTracks.recordEvent(
						wcpayTracks.events
							.SUBSCRIPTIONS_EMPTY_STATE_FINISH_SETUP
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
				is_connected: 'no',
			}
		);
	}, [] );

	return (
		<div className="woo_subscriptions_empty_state__container">
			<Image />
			<Description />
			<TOS />
			<ActionButtons />
		</div>
	);
};

render(
	<EmptyState />,
	document.querySelector( '#woo_subscriptions_empty_state' )
);
