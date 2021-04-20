/**
 * External dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { useStripe } from '@stripe/react-stripe-js';
import { __ } from '@wordpress/i18n';
// import isShallowEqual from '@wordpress/is-shallow-equal';

/**
 * Internal dependencies
 */
import {
	getPaymentRequest,
	updatePaymentRequest,
	canDoPaymentRequest,
	getErrorMessageFromNotice,
} from '../stripe-utils';

import {
	shippingAddressChangeHandler,
	shippingOptionChangeHandler,
	paymentMethodHandler,
} from '../event-handlers.js';

// import { useEventHandlers } from './use-event-handlers';

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
	emitResponse,
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

	const abortPayment = useCallback( ( paymentMethod ) => {
		paymentMethod.complete( 'fail' );
		setIsProcessing( false );
		setIsFinished( true );
	}, [] );

	const completePayment = useCallback( ( paymentMethod ) => {
		paymentMethod.complete( 'success' );
		setIsFinished( true );
		setIsProcessing( false );
	}, [] );

	// whenever paymentRequest changes, hook in event listeners.
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

			// const paymentMethodHandler = async ( paymentMethod ) => {
			// 	// We retrieve `allowPrepaidCard` like this to ensure we default to false in the
			// 	// event `allowPrepaidCard` isn't present on the server data object.
			// 	// - TODO: Get prepaid card
			// 	// const { allowPrepaidCard = false } = getStripeServerData();
			// 	const allowPrepaidCard = true;
			// 	if ( ! allowPrepaidCard && paymentMethod.source.card.funding ) {
			// 		setExpressPaymentError(
			// 			__(
			// 				"Sorry, we're not accepting prepaid cards at this time.",
			// 				'woocommerce-gateway-stripe'
			// 			)
			// 		);
			// 		return;
			// 	}

			// 	// Kick off checkout processing step.
			// 	const response = await api.paymentRequestCreateOrder(
			// 		paymentRequestType,
			// 		normalizeOrderDataForCheckout( paymentMethod )
			// 	);

			// 	// setPaymentRequestEventHandler(
			// 	// 	'paymentMethodEvent',
			// 	// 	paymentMethod
			// 	// );

			// 	if ( 'success' === response.result ) {
			// 		paymentMethod.complete( 'success' );
			// 		window.location = response.redirect;
			// 	} else {
			// 		paymentMethod.complete( 'fail' );
			// 		setExpressPaymentError(
			// 			getErrorMessageFromNotice( response.messages )
			// 		);
			// 	}
			// };

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
				( event ) => paymentMethodHandler( api, event )
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
		// setPaymentRequestEventHandler,
		setExpressPaymentError,
		onSubmit,
		onClose,
	] );

	return {
		paymentRequest,
		// paymentRequestEventHandlers,
		// clearPaymentRequestEventHandler,
		isProcessing,
		canMakePayment,
		onButtonClick,
		abortPayment,
		completePayment,
		paymentRequestType,
	};
};
