/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import ReactDOM from 'react-dom';

import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';
import { Button } from '@wordpress/components';

import connectedImage from '../../assets/images/subscriptions-empty-state-connected.svg';
import unconnectedImage from '../../assets/images/subscriptions-empty-state-unconnected.svg';

import './style.scss';

const { wcpay } = window;

const renderImage = ( isConnected ) => {
	if ( isConnected ) {
		return <img src={ connectedImage } alt="" />;
	}

	return <img src={ unconnectedImage } alt="" />;
};

const renderDescription = ( isConnected ) => {
	let description = '';

	if ( isConnected ) {
		description = __(
			'This is where you’ll see and manage all subscriptions in your store. Create a ' +
				'subscription product to turn one-time purchases into a steady income.',
			'woocommerce-payments'
		);
	} else {
		description = __(
			'Track recurring revenue and manage active subscriptions directly from your store’s ' +
				'dashboard — powered by WooCommerce Payments.',
			'woocommerce-payments'
		);
	}

	return (
		<p className="wcpay-empty-subscriptions__description">
			{ description }
		</p>
	);
};

const renderTOS = () => {
	return (
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
};

const renderButtons = ( isConnected ) => {
	return (
		<div className="wcpay-empty-subscriptions__button_container">
			{ ! isConnected && (
				<Button isPrimary href={ wcpay.connectUrl }>
					{ __( 'Finish setup', 'woocommerce-payments' ) }
				</Button>
			) }
			<Button isSecondary href={ wcpay.newProductUrl }>
				{ __( 'Create subscription product', 'woocommerce-payments' ) }
			</Button>
		</div>
	);
};

ReactDOM.render(
	<div className="wcpay-empty-subscriptions__container">
		{ renderImage( wcpay.isConnected ) }
		{ renderDescription( wcpay.isConnected ) }
		{ ! wcpay.isConnected && renderTOS() }
		{ renderButtons( wcpay.isConnected ) }
	</div>,
	document.querySelector( '#wcpay_subscriptions_empty_state' )
);
