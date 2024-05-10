/**
 * External dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { useStripe } from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import {
	shippingAddressChangeHandler,
	shippingOptionChangeHandler,
	paymentMethodHandler,
} from '../event-handlers.js';

import {
	getPaymentRequest,
	getPaymentRequestData,
	updatePaymentRequest,
	normalizeLineItems,
	displayLoginConfirmation,
} from '../utils/index.js';

export const useInitialization = ( {
	api,
	billing,
	shippingData,
	setExpressPaymentError,
	onClick,
	onClose,
} ) => {
	const stripe = useStripe();

	const [ paymentRequest, setPaymentRequest ] = useState( null );
	const [ isFinished, setIsFinished ] = useState( false );
	const [ paymentRequestType, setPaymentRequestType ] = useState( '' );

	// Create the initial paymentRequest object. Note, we can't do anything if stripe isn't available yet or we have zero total.
	useEffect( () => {
		if (
			! stripe ||
			! billing?.cartTotal?.value ||
			isFinished ||
			paymentRequest
		) {
			return;
		}

		const pr = getPaymentRequest( {
			stripe,
			total: billing?.cartTotal?.value,
			requestShipping: shippingData?.needsShipping,
			displayItems: normalizeLineItems( billing?.cartTotalItems ),
		} );

		pr.canMakePayment().then( ( result ) => {
			if ( result ) {
				setPaymentRequest( pr );
				if ( result.applePay ) {
					setPaymentRequestType( 'apple_pay' );
				} else if ( result.googlePay ) {
					setPaymentRequestType( 'google_pay' );
				} else {
					setPaymentRequestType( 'payment_request_api' );
				}
			}
		} );
	}, [
		stripe,
		paymentRequest,
		billing?.cartTotal?.value,
		isFinished,
		shippingData?.needsShipping,
		billing?.cartTotalItems,
	] );

	// It's not possible to update the `requestShipping` property in the `paymentRequest`
	// object, so when `needsShipping` changes, we need to reset the `paymentRequest` object.
	useEffect( () => {
		setPaymentRequest( null );
	}, [ shippingData.needsShipping ] );

	// When the payment button is clicked, update the request and show it.
	const onButtonClick = useCallback(
		( evt, pr ) => {
			// If login is required, display redirect confirmation dialog.
			if ( getPaymentRequestData( 'login_confirmation' ) ) {
				evt.preventDefault();
				displayLoginConfirmation( paymentRequestType );
				return;
			}

			setIsFinished( false );
			setExpressPaymentError( '' );
			updatePaymentRequest( {
				paymentRequest,
				total: billing?.cartTotal?.value,
				displayItems: normalizeLineItems( billing?.cartTotalItems ),
			} );
			onClick();

			// We must manually call payment request `show()` for custom buttons.
			if ( pr ) {
				pr.show();
			}
		},
		[
			onClick,
			paymentRequest,
			paymentRequestType,
			setExpressPaymentError,
			billing.cartTotal,
			billing.cartTotalItems,
		]
	);

	// Whenever paymentRequest changes, hook in event listeners.
	useEffect( () => {
		const cancelHandler = () => {
			setIsFinished( false );
			setPaymentRequest( null );
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

		paymentRequest?.on( 'shippingaddresschange', ( event ) =>
			shippingAddressChangeHandler( api, event )
		);

		paymentRequest?.on( 'shippingoptionchange', ( event ) =>
			shippingOptionChangeHandler( api, event )
		);

		paymentRequest?.on( 'paymentmethod', ( event ) =>
			paymentMethodHandler( api, completePayment, abortPayment, event )
		);

		paymentRequest?.on( 'cancel', cancelHandler );

		return () => {
			paymentRequest?.removeAllListeners();
		};
	}, [
		setExpressPaymentError,
		paymentRequest,
		api,
		setIsFinished,
		setPaymentRequest,
		onClose,
	] );

	return {
		paymentRequest,
		onButtonClick,
		paymentRequestType,
	};
};
