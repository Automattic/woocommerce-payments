/* global wcpayExpressCheckoutParams */

/**
 * External dependencies
 */
import React, { useState } from 'react';
import {
	ExpressCheckoutElement,
	useElements,
	useStripe,
} from '@stripe/react-stripe-js';

/**
 * ExpressCheckout express payment method component.
 *
 * @param {Object} props PaymentMethodProps.
 *
 * @return {ReactNode} Stripe Elements component.
 */
const ExpressCheckoutComponent = ( props ) => {
	const elements = useElements();
	const stripe = useStripe();
	const [ visibility, setVisibility ] = useState( 'hidden' );

	const buttonOptions = {
		paymentMethods: {
			applePay: 'always',
			googlePay: 'always',
			link: 'auto',
		},
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

export default ExpressCheckoutComponent;
