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
	updatePaymentRequest,
	canDoPaymentRequest,
	normalizeLineItems,
} from '../utils';

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
	const [ isProcessing, setIsProcessing ] = useState( false );
	const [ canMakePayment, setCanMakePayment ] = useState( false );
	const [ paymentRequestType, setPaymentRequestType ] = useState( '' );

	// Create the initial paymentRequest object. Note, we can't do anything if stripe isn't available yet or we have zero total.
	useEffect( () => {
		if (
			! stripe ||
			! billing?.cartTotal?.value ||
			isFinished ||
			isProcessing ||
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

		canDoPaymentRequest( pr ).then( ( result ) => {
			setPaymentRequest( pr );
			setPaymentRequestType( result.requestType || '' );
			setCanMakePayment( result.canPay );
		} );
	}, [ stripe, paymentRequest ] );

	// It's not possible to update the `requestShipping` property in the `paymentRequest`
	// object, so when `needsShipping` changes, we need to reset the `paymentRequest` object.
	useEffect( () => {
		setPaymentRequest( null );
	}, [ shippingData.needsShipping ] );

	// When the payment button is clicked, update the request and show it.
	const onButtonClick = useCallback( () => {
		setIsProcessing( true );
		setIsFinished( false );
		setExpressPaymentError( '' );
		updatePaymentRequest( {
			paymentRequest,
			total: billing?.cartTotal?.value,
			displayItems: normalizeLineItems( billing?.cartTotalItems ),
		} );
		onClick();
	}, [
		onClick,
		paymentRequest,
		setExpressPaymentError,
		billing.cartTotal,
		billing.cartTotalItems,
	] );

	const abortPayment = ( paymentMethod, message ) => {
		paymentMethod.complete( 'fail' );
		setIsFinished( true );
		setIsProcessing( false );
		setExpressPaymentError( message );
	};

	const completePayment = ( redirectUrl ) => {
		setIsFinished( true );
		setIsProcessing( false );
		window.location = redirectUrl;
	};

	// Whenever paymentRequest changes, hook in event listeners.
	useEffect( () => {
		const cancelHandler = () => {
			setIsFinished( false );
			setIsProcessing( false );
			setPaymentRequest( null );
			onClose();
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
	}, [ paymentRequest ] );

	return {
		paymentRequest,
		isProcessing,
		canMakePayment,
		onButtonClick,
		paymentRequestType,
	};
};
