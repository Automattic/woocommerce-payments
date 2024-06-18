/* global wcpayExpressCheckoutParams */

/**
 * External dependencies
 */
import { useCallback } from '@wordpress/element';
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

	const onCancel = () => {
		onClose();
	};

	const completePayment = ( redirectUrl ) => {
		window.location = redirectUrl;
	};

	const abortPayment = ( onConfirmEvent, message ) => {
		onConfirmEvent.paymentFailed( 'fail' );
		setExpressPaymentError( message );
	};

	const onButtonClick = useCallback(
		( event ) => {
			const options = {
				lineItems: normalizeLineItems( billing?.cartTotalItems ),
				emailRequired: true,
				shippingAddressRequired: shippingData?.needsShipping,
				phoneNumberRequired:
					wcpayExpressCheckoutParams?.checkout?.needs_payer_phone,
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
			onClick();
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
		onCancel,
		elements,
	};
};
