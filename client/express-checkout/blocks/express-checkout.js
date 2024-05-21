/* global wcpayExpressCheckoutParams */

/**
 * External dependencies
 */
import React, { useState } from 'react';
import {
	Elements,
	ExpressCheckoutElement,
	useElements,
	useStripe,
} from '@stripe/react-stripe-js';

export const ExpressCheckoutContainer = ( props ) => {
	const { stripe } = props;

	const options = {
		mode: 'payment',
		amount: 1099,
		currency: 'usd',
	};

	return (
		<Elements stripe={ stripe } options={ options }>
			<ExpressCheckout />
		</Elements>
	);
};

/**
 * ExpressCheckout express payment method component.
 *
 * @param {Object} props PaymentMethodProps.
 *
 * @return {ReactNode} Stripe Elements component.
 */
export const ExpressCheckout = ( props ) => {
	const stripe = useStripe();
	const elements = useElements();
	const [ visibility, setVisibility ] = useState( 'hidden' );

	const buttonOptions = {
		buttonType: {
			googlePay: wcpayExpressCheckoutParams.button.type,
			applePay: wcpayExpressCheckoutParams.button.type,
		},
	};

	const onReady = ( { availablePaymentMethods } ) => {
		console.log( 'Ready' + availablePaymentMethods );

		if ( ! availablePaymentMethods ) {
			// No buttons will show
		} else {
			// Optional: Animate in the Element
			setVisibility( 'initial' );
		}
	};

	const onConfirm = async () => {
		console.log( 'Confirmed' );
		const { error } = stripe.confirmPayment( {
			elements,
			confirmParams: {
				return_url: 'https://example.com/order/123/complete',
			},
		} );

		if ( error ) {
			// This point is reached only if there's an immediate error when confirming the payment.
			// Show the error to your customer(for example, payment details incomplete).
		} else {
			// Your customer will be redirected to your `return_url`.
		}
	};

	return (
		<ExpressCheckoutElement
			options={ buttonOptions }
			onReady={ onReady }
			onConfirm={ onConfirm }
		/>
	);
};
