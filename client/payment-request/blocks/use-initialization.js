/**
 * External dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { useStripe } from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import {
	getPaymentRequest,
	updatePaymentRequest,
	canDoPaymentRequest,
} from '../stripe-utils';

import {
	shippingAddressChangeHandler,
	shippingOptionChangeHandler,
	paymentMethodHandler,
} from '../event-handlers.js';

/**
 * @typedef {import('../stripe-utils/type-defs').StripePaymentRequest} StripePaymentRequest
 */

export const useInitialization = ( {
	api,
	billing,
	shippingData,
	setExpressPaymentError,
	onClick,
	onClose,
	onSubmit,
} ) => {
	const stripe = useStripe();
	/**
	 * @type {[ StripePaymentRequest|null, function( StripePaymentRequest ):void]}
	 */
	// @ts-ignore
	const [ paymentRequest, setPaymentRequest ] = useState( null );
	const [ isFinished, setIsFinished ] = useState( false );
	const [ isProcessing, setIsProcessing ] = useState( false );
	const [ canMakePayment, setCanMakePayment ] = useState( false );
	const [ paymentRequestType, setPaymentRequestType ] = useState( '' );

	// Create the initial paymentRequest object. Note, we can't do anything if stripe isn't available yet or we have zero total.
	useEffect( () => {
		if (
			! stripe ||
			! billing.cartTotal.value ||
			isFinished ||
			isProcessing ||
			paymentRequest
		) {
			return;
		}
		const pr = getPaymentRequest( {
			total: billing.cartTotal,
			currencyCode: billing.currency.code.toLowerCase(),
			countryCode: 'US', // - TODO: get country code
			shippingRequired: shippingData.needsShipping,
			cartTotalItems: billing.cartTotalItems,
			stripe,
		} );
		canDoPaymentRequest( pr ).then( ( result ) => {
			setPaymentRequest( pr );
			setPaymentRequestType( result.requestType || '' );
			setCanMakePayment( result.canPay );
		} );
	}, [
		billing.cartTotal,
		billing.currency.code,
		shippingData.needsShipping,
		billing.cartTotalItems,
		stripe,
		isProcessing,
		isFinished,
		paymentRequest,
	] );

	// When the payment button is clicked, update the request and show it.
	const onButtonClick = useCallback( () => {
		setIsProcessing( true );
		setIsFinished( false );
		setExpressPaymentError( '' );
		updatePaymentRequest( {
			// @ts-ignore
			paymentRequest,
			total: billing.cartTotal,
			currencyCode: billing.currency.code.toLowerCase(),
			cartTotalItems: billing.cartTotalItems,
		} );
		onClick();
	}, [
		onClick,
		paymentRequest,
		setExpressPaymentError,
		billing.cartTotal,
		billing.currency.code,
		billing.cartTotalItems,
	] );

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

			// @ts-ignore
			shippingAddressChangeEvent = paymentRequest.on(
				'shippingaddresschange',
				( event ) => shippingAddressChangeHandler( api, event )
			);
			// @ts-ignore
			shippingOptionChangeEvent = paymentRequest.on(
				'shippingoptionchange',
				( event ) => shippingOptionChangeHandler( api, event )
			);
			// @ts-ignore
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
			// @ts-ignore
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
	}, [
		paymentRequest,
		canMakePayment,
		isProcessing,
		setExpressPaymentError,
		onSubmit,
		onClose,
	] );

	return {
		paymentRequest,
		isProcessing,
		canMakePayment,
		onButtonClick,
		abortPayment,
		completePayment,
		paymentRequestType,
	};
};
