/**
 * External dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';

/**
 * Internal dependencies
 */
import {
	shippingAddressChangeHandler,
	shippingOptionChangeHandler,
	paymentMethodHandler,
} from '../event-handlers.js';

import { getPaymentRequestOptions, canDoPaymentRequest } from '../utils';

export const useInitialization = ( {
	api,
	setExpressPaymentError,
	onClick,
	onClose,
} ) => {
	const stripe = api.getStripe();

	const [ paymentRequest, setPaymentRequest ] = useState( null );
	const [ isFinished, setIsFinished ] = useState( false );
	const [ isProcessing, setIsProcessing ] = useState( false );
	const [ canMakePayment, setCanMakePayment ] = useState( false );
	const [ paymentRequestType, setPaymentRequestType ] = useState( '' );

	// Create the initial paymentRequest object. Note, we can't do anything if stripe isn't available yet or we have zero total.
	useEffect( () => {
		if ( ! stripe || isFinished || isProcessing || paymentRequest ) {
			return;
		}

		const pr = stripe.paymentRequest( getPaymentRequestOptions() );

		canDoPaymentRequest( pr ).then( ( result ) => {
			setPaymentRequest( pr );
			setPaymentRequestType( result.requestType || '' );
			setCanMakePayment( result.canPay );
		} );
	}, [ stripe ] );

	// When the payment button is clicked, update the request and show it.
	const onButtonClick = useCallback( () => {
		setIsProcessing( true );
		setIsFinished( false );
		setExpressPaymentError( '' );
		onClick();
	}, [] );

	const abortPayment = useCallback( ( paymentMethod, message ) => {
		paymentMethod.complete( 'fail' );
		setIsFinished( true );
		setIsProcessing( false );
		setExpressPaymentError( message );
	}, [] );

	const completePayment = useCallback( ( redirectUrl ) => {
		setIsFinished( true );
		setIsProcessing( false );
		window.location = redirectUrl;
	}, [] );

	// Whenever paymentRequest changes, hook in event listeners.
	useEffect( () => {
		const noop = { removeAllListeners: () => void null };
		let shippingAddressChangeEvent = noop,
			shippingOptionChangeEvent = noop,
			paymentMethodChangeEvent = noop,
			cancelChangeEvent = noop;

		if ( paymentRequest ) {
			const cancelHandler = () => {
				setIsFinished( false );
				setIsProcessing( false );
				setPaymentRequest( null );
				onClose();
			};

			shippingAddressChangeEvent = paymentRequest.on(
				'shippingaddresschange',
				( event ) => shippingAddressChangeHandler( api, event )
			);

			shippingOptionChangeEvent = paymentRequest.on(
				'shippingoptionchange',
				( event ) => shippingOptionChangeHandler( api, event )
			);

			paymentMethodChangeEvent = paymentRequest.on(
				'paymentmethod',
				( event ) =>
					paymentMethodHandler(
						api,
						completePayment,
						abortPayment,
						event
					)
			);

			cancelChangeEvent = paymentRequest.on( 'cancel', cancelHandler );
		}

		return () => {
			if ( paymentRequest ) {
				shippingAddressChangeEvent.removeAllListeners();
				shippingOptionChangeEvent.removeAllListeners();
				paymentMethodChangeEvent.removeAllListeners();
				cancelChangeEvent.removeAllListeners();
			}
		};
	}, [ paymentRequest ] );

	return {
		paymentRequest,
		isProcessing,
		canMakePayment,
		onButtonClick,
		paymentRequestType,
	};
};
