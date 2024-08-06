/* global wcpayPaymentRequestParams */

/**
 * External dependencies
 */
import { Elements, PaymentRequestButtonElement } from '@stripe/react-stripe-js';
import { recordUserEvent } from 'tracks';
import { useEffect, useState } from 'react';

/**
 * Internal dependencies
 */
import { useInitialization } from './use-initialization';
import { getPaymentRequestData } from '../frontend-utils';

/**
 * PaymentRequestExpressComponent
 *
 * @param {Object} props Incoming props.
 *
 * @return {ReactNode} Payment Request button component.
 */
const PaymentRequestExpressComponent = ( {
	api,
	billing,
	shippingData,
	setExpressPaymentError,
	onClick,
	onClose,
	onPaymentRequestAvailable,
	cartData,
} ) => {
	// TODO: Don't display custom button when result.requestType
	// is `apple_pay` or `google_pay`.
	const {
		paymentRequest,
		// paymentRequestType,
		onButtonClick,
	} = useInitialization( {
		api,
		billing,
		shippingData,
		setExpressPaymentError,
		onClick,
		onClose,
		cartData,
	} );

	useEffect( () => {
		if ( paymentRequest ) {
			const orderAttribution = window?.wc_order_attribution;
			if ( orderAttribution ) {
				orderAttribution.setOrderTracking(
					orderAttribution.params.allowTracking
				);
			}
		}
	}, [ paymentRequest ] );

	const { type, theme, height } = getPaymentRequestData( 'button' );

	const paymentRequestButtonStyle = {
		paymentRequestButton: {
			type,
			theme,
			height: height + 'px',
		},
	};

	if ( ! paymentRequest ) {
		return null;
	}

	let paymentRequestType = '';

	// Check the availability of the Payment Request API first.
	paymentRequest.canMakePayment().then( ( result ) => {
		if ( ! result ) {
			return;
		}

		// Set the payment request type.
		if ( result.applePay ) {
			paymentRequestType = 'apple_pay';
		} else if ( result.googlePay ) {
			paymentRequestType = 'google_pay';
		}
		onPaymentRequestAvailable( paymentRequestType );
	} );

	const onPaymentRequestButtonClick = ( event ) => {
		onButtonClick( event, paymentRequest );

		const paymentRequestTypeEvents = {
			google_pay: 'gpay_button_click',
			apple_pay: 'applepay_button_click',
		};

		if ( paymentRequestTypeEvents.hasOwnProperty( paymentRequestType ) ) {
			const paymentRequestEvent =
				paymentRequestTypeEvents[ paymentRequestType ];
			recordUserEvent( paymentRequestEvent, {
				source: wcpayPaymentRequestParams?.button_context,
			} );
		}
	};

	return (
		<div>
			<PaymentRequestButtonElement
				onClick={ onPaymentRequestButtonClick }
				options={ {
					style: paymentRequestButtonStyle,
					paymentRequest,
				} }
			/>
			<wc-order-attribution-inputs id="wcpay-express-checkout__order-attribution-inputs"></wc-order-attribution-inputs>
		</div>
	);
};

/**
 * PaymentRequestExpress express payment method component.
 *
 * @param {Object} props PaymentMethodProps.
 *
 * @return {ReactNode} Stripe Elements component.
 */
export const PaymentRequestExpress = ( props ) => {
	const { stripe } = props;
	const [ paymentRequestType, setPaymentRequestType ] = useState( false );

	const handlePaymentRequestAvailability = ( paymentType ) => {
		setPaymentRequestType( paymentType );
	};

	useEffect( () => {
		if ( paymentRequestType ) {
			const paymentRequestTypeEvents = {
				google_pay: 'gpay_button_load',
				apple_pay: 'applepay_button_load',
			};

			if (
				paymentRequestTypeEvents.hasOwnProperty( paymentRequestType )
			) {
				const event = paymentRequestTypeEvents[ paymentRequestType ];
				recordUserEvent( event, {
					source: wcpayPaymentRequestParams?.button_context,
				} );
			}
		}
	}, [ paymentRequestType ] );

	return (
		<Elements stripe={ stripe }>
			<PaymentRequestExpressComponent
				{ ...props }
				onPaymentRequestAvailable={ handlePaymentRequestAvailability }
			/>
		</Elements>
	);
};
