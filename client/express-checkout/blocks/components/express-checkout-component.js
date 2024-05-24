
/**
 * External dependencies
 */
import React, { useState } from 'react';
import {
	ExpressCheckoutElement,
	useElements,
	useStripe,
} from '@stripe/react-stripe-js';
import { useExpressCheckout } from '../hooks/use-express-checkout';

/**
 * ExpressCheckout express payment method component.
 *
 * @param {Object} props PaymentMethodProps.
 *
 * @return {ReactNode} Stripe Elements component.
 */
const ExpressCheckoutComponent = ( {
	api,
	billing,
	shippingData,
	setExpressPaymentError,
	onClick,
	onClose,
	onPaymentRequestAvailable,
} ) => {
	const { buttonOptions, onButtonClick, onConfirm } = useExpressCheckout( {
		billing,
		onClick,
		setExpressPaymentError,
	} );

	return (
		<ExpressCheckoutElement
			options={ buttonOptions }
			onClick={ onButtonClick }
			onConfirm={ onConfirm }
		/>
	);
};

export default ExpressCheckoutComponent;
