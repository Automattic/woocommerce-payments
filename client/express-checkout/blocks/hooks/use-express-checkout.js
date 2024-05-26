/* global wcpayExpressCheckoutParams */

/**
 * External dependencies
 */
import { useCallback, useState, useEffect } from '@wordpress/element';
import { useStripe, useElements } from '@stripe/react-stripe-js';
import { normalizeLineItems } from 'wcpay/express-checkout/utils';
import { onConfirmHandler } from 'wcpay/express-checkout/event-handlers';

export const useExpressCheckout = ( {
	api,
	billing,
	shippingData,
	onClick,
	onClose,
	setExpressPaymentError,
} ) => {
	const stripe = useStripe();
	const elements = useElements();

	const [ isFinished, setIsFinished ] = useState( false );

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

	const cancelHandler = () => {
		setIsFinished( false );
		onClose();
	};

	const completePayment = ( redirectUrl ) => {
		setIsFinished( true );
		window.location = redirectUrl;
	};

	const abortPayment = ( paymentMethod, message ) => {
		paymentMethod.complete( 'fail' );
		setIsFinished( true );
		setExpressPaymentError( message );
	};

	// When the button is clicked, update the data and show it.
	const onButtonClick = useCallback(
		( event ) => {
			setIsFinished( false );

			console.log( shippingData?.shippingRates );

			const options = {
				lineItems: normalizeLineItems( billing?.cartTotalItems ),
				emailRequired: true,
				shippingAddressRequired: shippingData?.needsShipping,
				shippingRates: shippingData?.shippingRates[ 0 ].shipping_rates.map(
					( r ) => {
						return {
							id: r.rate_id,
							amount: parseInt( r.price, 10 ),
							displayName: r.name,
						};
					}
				),
			};
			event.resolve( options );
			onClick();
		},
		[
			onClick,
			setExpressPaymentError,
			billing.cartTotalItems,
			shippingData.needsShipping,
		]
	);

	const onConfirm = async ( event ) => {
		console.log( 'Confirmed' );
		onConfirmHandler( api, completePayment, abortPayment, event );

		event.resolve();

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
