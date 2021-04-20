/**
 * External dependencies
 */
import { Elements, PaymentRequestButtonElement } from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import { useInitialization } from './use-initialization';
import { ThreeDSecurePaymentHandler } from '../three-d-secure';
import { GooglePayButton, shouldUseGooglePayBrand } from './branded-buttons';
import { CustomButton } from './custom-button';

/**
 * @typedef {import('../stripe-utils/type-defs').Stripe} Stripe
 * @typedef {import('../stripe-utils/type-defs').StripePaymentRequest} StripePaymentRequest
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').RegisteredPaymentMethodProps} RegisteredPaymentMethodProps
 */

/**
 * @typedef {Object} WithStripe
 *
 * @property {Stripe} [stripe] Stripe api (might not be present)
 */

/**
 * @typedef {RegisteredPaymentMethodProps & WithStripe} StripeRegisteredPaymentMethodProps
 */

/**
 * PaymentRequestExpressComponent
 *
 * @param {StripeRegisteredPaymentMethodProps} props Incoming props
 *
 * @return {ReactNode} Payment Request button component.
 */
const PaymentRequestExpressComponent = ( {
	api,
	shippingData,
	billing,
	// eventRegistration,
	onSubmit,
	setExpressPaymentError,
	emitResponse,
	onClick,
	onClose,
} ) => {
	const {
		paymentRequest,
		// paymentRequestEventHandlers,
		// clearPaymentRequestEventHandler,
		// isProcessing,
		canMakePayment,
		onButtonClick,
		// abortPayment,
		// completePayment,
		// paymentRequestType,
	} = useInitialization( {
		api,
		billing,
		shippingData,
		setExpressPaymentError,
		onClick,
		onClose,
		onSubmit,
		emitResponse,
	} );

	// Use pre-blocks settings until we merge the two distinct settings objects.
	/* global wcpayPaymentRequestParams */
	const isBranded = wcpayPaymentRequestParams.button.is_branded;
	const brandedType = wcpayPaymentRequestParams.button.branded_type;
	const isCustom = wcpayPaymentRequestParams.button.is_custom;

	// locale is not a valid value for the paymentRequestButton style.
	// Make sure `theme` defaults to 'dark' if it's not found in the server provided configuration.
	// - TODO: Get button theme
	const theme = 'dark';
	// - TODO: Add internal shared dependency here.
	const paymentRequestButtonStyle = {
		paymentRequestButton: {
			// Not implemented branded buttons default to Stripe's button.
			// Apple Pay buttons can also fall back to Stripe's button, as it's already branded.
			// Set button type to default or buy, depending on branded type, to avoid issues with Stripe.
			type: isBranded && 'long' === brandedType ? 'buy' : 'default',
			theme,
			height: '48px',
		},
	};

	if ( ! canMakePayment || ! paymentRequest ) {
		return null;
	}

	if ( isBranded && shouldUseGooglePayBrand() ) {
		return (
			<GooglePayButton
				onClick={ () => {
					onButtonClick();
					// Since we're using a custom button we must manually call
					// `paymentRequest.show()`.
					paymentRequest.show();
				} }
			/>
		);
	}

	if ( isCustom ) {
		return (
			<CustomButton
				onClick={ () => {
					onButtonClick();
					// Since we're using a custom button we must manually call
					// `paymentRequest.show()`.
					paymentRequest.show();
				} }
			/>
		);
	}

	return (
		<PaymentRequestButtonElement
			onClick={ onButtonClick }
			options={ {
				// @ts-ignore
				style: paymentRequestButtonStyle,
				// @ts-ignore
				paymentRequest,
			} }
		/>
	);
};

/**
 * PaymentRequestExpress with stripe provider
 *
 * @param {StripeRegisteredPaymentMethodProps} props PaymentMethodProps.
 *
 * @return {ReactNode} Stripe Elements component.
 */
export const PaymentRequestExpress = ( props ) => {
	// Make sure `locale` defaults to 'en_US' if it's not found in the server provided
	// configuration.
	// - TODO: Get button locale
	const locale = 'en_US';
	const { stripe } = props;
	return (
		<Elements stripe={ stripe } locale={ locale }>
			<PaymentRequestExpressComponent { ...props } />
			<ThreeDSecurePaymentHandler { ...props } />
		</Elements>
	);
};
