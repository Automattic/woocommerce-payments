/**
 * External dependencies
 */
import { useCallback } from '@wordpress/element';
import { useStripe, useElements } from '@stripe/react-stripe-js';
/**
 * Internal dependencies
 */
import {
	getExpressCheckoutButtonStyleSettings,
  getExpressCheckoutData,
	normalizeLineItems,
} from 'wcpay/express-checkout/utils';
import {
	onClickHandler,
	onConfirmHandler,
	onReadyHandler,
} from 'wcpay/express-checkout/event-handlers';

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

	const buttonOptions = getExpressCheckoutButtonStyleSettings();

	const onCancel = () => {
		onClose();
	};

	const completePayment = ( redirectUrl ) => {
		window.location = redirectUrl;
	};

	const abortPayment = ( onConfirmEvent, message ) => {
		onConfirmEvent.paymentFailed( { reason: 'fail' } );
		setExpressPaymentError( message );
	};

	const onButtonClick = useCallback(
		( event ) => {
			const options = {
				lineItems: normalizeLineItems( billing?.cartTotalItems ),
				emailRequired: true,
				shippingAddressRequired: shippingData?.needsShipping,
				phoneNumberRequired:
					getExpressCheckoutData( 'checkout' )?.needs_payer_phone ??
					false,
				shippingRates: shippingData?.shippingRates[ 0 ]?.shipping_rates?.map(
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
			// Click event from WC Blocks.
			onClick();
			// Global click event handler from WooPayments to ECE.
			onClickHandler( event );
		},
		[
			onClick,
			billing.cartTotalItems,
			shippingData.needsShipping,
			shippingData.shippingRates,
		]
	);

	const onConfirm = async ( event ) => {
		onConfirmHandler(
			api,
			stripe,
			elements,
			completePayment,
			abortPayment,
			event
		);
	};

	return {
		buttonOptions,
		onButtonClick,
		onConfirm,
		onReady: onReadyHandler,
		onCancel,
		elements,
	};
};
