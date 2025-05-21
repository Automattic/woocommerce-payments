/**
 * External dependencies
 */
import React from 'react';
import { createRoot } from 'react-dom/client';

/**
 * Internal Dependencies
 */
import PaymentGatewaysConfirmation from './payment-gateways-confirmation';

const paymentGatewaysContainer = document.getElementById(
	'wcpay-payment-gateways-container'
);
if ( paymentGatewaysContainer ) {
	const root = createRoot(paymentGatewaysContainer);
	root.render( <PaymentGatewaysConfirmation /> );
}
