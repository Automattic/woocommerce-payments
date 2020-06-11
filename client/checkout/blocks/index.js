/**
 * External dependencies
 */
import { Component } from 'react';
import { useEffect } from '@wordpress/element';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { loadStripe } from '@stripe/stripe-js';
import {
	Elements,
	CardElement,
	useElements,
	ElementsConsumer,
} from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import WCPayAPI from './../api';
import generatePaymentMethod from './generate-payment-method.js';
import confirmCardPayment from './confirm-card-payment.js';

const PAYMENT_METHOD_NAME = 'woocommerce_payments';

const api = new WCPayAPI( {
	publishableKey: wcpay_config.publishableKey,
	accountId: wcpay_config.accountId,
} );
const stripe = api.getStripe();


const WCPayFields = ( { elements, eventRegistration: { onPaymentProcessing, onCheckoutAfterProcessingWithSuccess } } ) => {
	useEffect( () => {
		return onPaymentProcessing( () => {
			const paymentElements = {
				card: elements.getElement( CardElement ),
			};

			return generatePaymentMethod( stripe, paymentElements );
		} );
	}, [ elements, stripe ] );

	useEffect( () => {
		return onCheckoutAfterProcessingWithSuccess( ( { processingResponse: { paymentDetails } } ) => {
			return confirmCardPayment( stripe, paymentDetails );
		} );
	}, [ elements, stripe ] );

	const options = {
		hidePostalCode: true,
		classes: {
			base: 'wcpay-card-mounted',
		},
	};

	return <CardElement options={ options } />;
};

const ConsumableWCPayFields = ( props ) => (
	<Elements stripe={ stripe }>
		<ElementsConsumer>
			{ ( { elements } ) => (
				<WCPayFields elements={ elements } { ...props } />
			) }
		</ElementsConsumer>
	</Elements>
);

registerPaymentMethod(
	( PaymentMethodConfig ) => new PaymentMethodConfig( {
		name: PAYMENT_METHOD_NAME,
		content: <ConsumableWCPayFields />,
		edit: <ConsumableWCPayFields />,
		canMakePayment: () => true,
		paymentMethodId: PAYMENT_METHOD_NAME,
		label: 'Credit Card',
		ariaLabel: 'Credit Card',
		placeOrderButtonLabel: 'Place order with WCPay',
	} )
);
