/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './style.scss';

const PaymentMethodsEmptyState = () => {
	return (
		<>
			<div className="payment-methods__no-payment-methods-illustration"></div>
			<p>
				{ __(
					'Add your customer’s preferred payment methods to give your sales a healthy boost.',
					'woocommerce-payments'
				) }
			</p>
		</>
	);
};

export default PaymentMethodsEmptyState;
