/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';

/**
 * Internal Dependencies
 */
import PaymentGatewaysConfirmation from './payment-gateways-confirmation';

const paymentGatewaysContainer = document.getElementById(
	'wcpay-payment-gateways-container'
);
if ( paymentGatewaysContainer ) {
	ReactDOM.render(
		<PaymentGatewaysConfirmation />,
		paymentGatewaysContainer
	);
}
