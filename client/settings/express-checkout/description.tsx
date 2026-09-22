/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { ExternalLink } from '@wordpress/components';

const ExpressCheckoutDescription = () => (
	<>
		<h2>{ __( 'Express checkouts', 'woocommerce-payments' ) }</h2>
		<p>
			{ __(
				'Let your customers use their favorite express payment methods and digital wallets ' +
					'for faster, more secure checkouts across different parts of your store.',
				'woocommerce-payments'
			) }
		</p>
		<ExternalLink href="https://woocommerce.com/document/woopayments/settings-guide/#express-checkouts">
			{ __( 'Learn more', 'woocommerce-payments' ) }
		</ExternalLink>
	</>
);

export default ExpressCheckoutDescription;
