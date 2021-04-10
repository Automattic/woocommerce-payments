/**
 * External dependencies
 */
import { useEffect, useState, useRef, useCallback } from '@wordpress/element';
import { useStripe } from '@stripe/react-stripe-js';
import { __ } from '@wordpress/i18n';
import isShallowEqual from '@wordpress/is-shallow-equal';

/**
 * Internal dependencies
 */
import {
	getPaymentRequest,
	updatePaymentRequest,
	canDoPaymentRequest,
	normalizeShippingAddressForCheckout,
	normalizeShippingOptionSelectionsForCheckout,
	pluckAddress,
	normalizeShippingOptions,
} from '../stripe-utils';
import { useEventHandlers } from './use-event-handlers';

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
	const currentShipping = useRef( shippingData );
	const {
		paymentRequestEventHandlers,
		clearPaymentRequestEventHandler,
		setPaymentRequestEventHandler,
	} = useEventHandlers();

	// Update refs when any change.
	useEffect( () => {
		currentShipping.current = shippingData;
	}, [ shippingData ] );

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

			const shippingAddressChangeHandler = async ( event ) => {
				const newShippingAddress = normalizeShippingAddressForCheckout(
					event.shippingAddress
				);
				if (
					isShallowEqual(
						pluckAddress( newShippingAddress ),
						pluckAddress( currentShipping.current.shippingAddress )
					)
				) {
					// the address is the same so no change needed.
					event.updateWith( {
						status: 'success',
						shippingOptions: normalizeShippingOptions(
							currentShipping.current.shippingRates
						),
					} );
				} else {
					// the address is different so let's set the new address and
					// register the handler to be picked up by the shipping rate
					// change event.
					const response = await api.paymentRequestCalculateShippingOptions(
						newShippingAddress
					);

					event.updateWith( {
						status: response.result,
						shippingOptions: response.shipping_options,
						total: response.total,
						displayItems: response.displayItems,
					} );

					setPaymentRequestEventHandler(
						'shippingAddressChange',
						event
					);
				}
			};

			const shippingOptionChangeHandler = ( event ) => {
				currentShipping.current.setSelectedRates(
					normalizeShippingOptionSelectionsForCheckout(
						event.shippingOption
					)
				);
				setPaymentRequestEventHandler( 'shippingOptionChange', event );
			};

			const paymentMethodHandler = async ( paymentMethod ) => {
				// We retrieve `allowPrepaidCard` like this to ensure we default to false in the
				// event `allowPrepaidCard` isn't present on the server data object.
				// - TODO: Get prepaid card
				// const { allowPrepaidCard = false } = getStripeServerData();
				const allowPrepaidCard = true;
				if (
					// eslint-disable-next-line no-undef
					! allowPrepaidCard &&
					paymentMethod.source.card.funding
				) {
					setExpressPaymentError(
						__(
							"Sorry, we're not accepting prepaid cards at this time.",
							'woocommerce-gateway-stripe'
						)
					);
					return;
				}
				setPaymentRequestEventHandler( 'sourceEvent', paymentMethod );
				// kick off checkout processing step.


				setPaymentRequestEventHandler(
					'paymentMethodEvent',
					paymentMethod
				);
			};

			// @ts-ignore
			shippingAddressChangeEvent = paymentRequest.on(
				'shippingaddresschange',
				shippingAddressChangeHandler
			);
			// @ts-ignore
			shippingOptionChangeEvent = paymentRequest.on(
				'shippingoptionchange',
				shippingOptionChangeHandler
			);
			// @ts-ignore
			paymentMethodChangeEvent = paymentRequest.on(
				'paymentmethod',
				paymentMethodHandler
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
		setPaymentRequestEventHandler,
		setExpressPaymentError,
		onSubmit,
		onClose,
	] );

	return {
		paymentRequest,
		paymentRequestEventHandlers,
		clearPaymentRequestEventHandler,
		isProcessing,
		canMakePayment,
		onButtonClick,
		abortPayment,
		completePayment,
		paymentRequestType,
	};
};
