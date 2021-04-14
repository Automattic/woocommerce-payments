/**
 * External dependencies
 */
import { Elements, PaymentRequestButtonElement } from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import { useInitialization } from './use-initialization';
import { useCheckoutSubscriptions } from './use-checkout-subscriptions';
import { ThreeDSecurePaymentHandler } from '../three-d-secure';
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
	eventRegistration,
	onSubmit,
	setExpressPaymentError,
	emitResponse,
	onClick,
	onClose,
} ) => {
	const {
		paymentRequest,
		paymentRequestEventHandlers,
		clearPaymentRequestEventHandler,
		isProcessing,
		canMakePayment,
		onButtonClick,
		abortPayment,
		completePayment,
		paymentRequestType,
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
	useCheckoutSubscriptions( {
		canMakePayment,
		isProcessing,
		eventRegistration,
		paymentRequestEventHandlers,
		clearPaymentRequestEventHandler,
		billing,
		shippingData,
		emitResponse,
		paymentRequestType,
		completePayment,
		abortPayment,
	} );

	// locale is not a valid value for the paymentRequestButton style.
	// Make sure `theme` defaults to 'dark' if it's not found in the server provided configuration.
	// - TODO: Get button theme
	const theme = 'dark';
	// - TODO: Add internal shared dependency here.
	const paymentRequestButtonStyle = {
		paymentRequestButton: {
			type: 'default',
			theme,
			height: '48px',
		},
	};

	// Use pre-blocks settings until we merge the two distinct settings objects.
	/* global wcpayPaymentRequestParams */
	const isCustom = wcpayPaymentRequestParams.button.is_custom;

	if ( ! canMakePayment || ! paymentRequest ) {
		return null;
	}

	if ( isCustom ) {
		return <CustomButton onButtonClicked={ onButtonClick } />;
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
