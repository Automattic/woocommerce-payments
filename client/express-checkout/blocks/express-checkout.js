/* global wcpayPaymentRequestParams */

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

	return (
		<Elements stripe={ stripe } options={ options }>
			<div width="100%">
				<ExpressCheckoutElement { ...props } />
			</div>
		</Elements>
	);
};
