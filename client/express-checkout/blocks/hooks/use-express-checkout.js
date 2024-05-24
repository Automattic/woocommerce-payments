/* global wcpayExpressCheckoutParams */

/**
 * External dependencies
 */
import { useCallback, useState } from '@wordpress/element';
import { useStripe, useElements } from '@stripe/react-stripe-js';
import { normalizeLineItems } from 'wcpay/express-checkout/utils';

export const useExpressCheckout = ( {
	billing,
	onClick,
	setExpressPaymentError,
} ) => {
	const stripe = useStripe();
	const elements = useElements();

	const [ isFinished, setIsFinished ] = useState( false );

	// useEffect( () => {
	// 	elements.on( 'click', ( event ) => {
	// 		const options = {
	// 			emailRequired: true,
	// 		};
	// 		event.resolve( options );
	// 	} );
	// } );

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

	// When the button is clicked, update the data and show it.
	const onButtonClick = useCallback(
		( event ) => {
			setIsFinished( false );
			setExpressPaymentError( 'TESTANDO SOM' );

			const options = {
				lineItems: normalizeLineItems( billing?.cartTotalItems ),
				emailRequired: true,
			};
			event.resolve( options );
			onClick();
		},
		[ onClick, setExpressPaymentError, billing.cartTotalItems ]
	);

    const onConfirm = async () => {
		console.log( 'Confirmed' );
		// Uncaught (in promise) IntegrationError:
		// You must pass in a clientSecret when calling stripe.confirmPayment().

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

	return {
		buttonOptions,
		onButtonClick,
		onConfirm,
	};
};
