/* global wcpayExpressCheckoutParams */

/**
 * External dependencies
 */
import { Elements, ExpressCheckoutElement } from '@stripe/react-stripe-js';

/**
 * ExpressCheckout express payment method component.
 *
 * @param {Object} props PaymentMethodProps.
 *
 * @return {ReactNode} Stripe Elements component.
 */
export const ExpressCheckout = ( props ) => {
	const { stripe } = props;

	const options = {
		mode: 'payment',
		amount: 1099,
		currency: 'usd',
	};

	const buttonOptions = {
		buttonType: {
			googlePay: wcpayExpressCheckoutParams.button.type,
			applePay: wcpayExpressCheckoutParams.button.type,
		},
	};

	return (
		<Elements stripe={ stripe } options={ options }>
			<ExpressCheckoutElement options={ buttonOptions } />
		</Elements>
	);
};
