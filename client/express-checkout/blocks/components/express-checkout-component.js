/**
 * External dependencies
 */
import React, { useCallback } from 'react';
import { ExpressCheckoutElement } from '@stripe/react-stripe-js';
import { shippingAddressChangeHandler } from '../../event-handlers';
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
		api,
		billing,
		shippingData,
		onClick,
		setExpressPaymentError,
	} );

	const onShippingAddressChange = ( event ) => {
		shippingAddressChangeHandler( api, event );
	};

	return (
		<ExpressCheckoutElement
			options={ buttonOptions }
			onClick={ onButtonClick }
			onConfirm={ onConfirm }
			onShippingAddressChange={ onShippingAddressChange }
		/>
	);
};

export default ExpressCheckoutComponent;
