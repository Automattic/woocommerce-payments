/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Link } from 'react-router-dom';

/**
 * Internal dependencies
 */

export const OnboardingSidebar = () => {
	return (
		<div className="woocommerce-woopayments-onboarding-modal__sidebar">
			<h2>{ __( 'Set up WooPayments', 'woocommerce' ) }</h2>

			<ul className="woocommerce-woopayments-onboarding-modal__sidebar--navigation">
				<li>
					<Link to="/">
						{ __( 'Choose your payment methods', 'woocommerce' ) }
					</Link>
				</li>
			</ul>
		</div>
	);
};
