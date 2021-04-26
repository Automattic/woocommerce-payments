/**
 * External dependencies
 */
import { Elements, PaymentRequestButtonElement } from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import { getPaymentRequestData } from '../utils';
import { useInitialization } from './use-initialization';
import { GooglePayButton, shouldUseGooglePayBrand } from './branded-buttons';
import { CustomButton } from './custom-button';

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
	onSubmit,
	setExpressPaymentError,
	emitResponse,
	onClick,
	onClose,
} ) => {
	const {
		paymentRequest,
		// TODO: Add loading indicator when isProcessing.
		// isProcessing,
		canMakePayment,
		onButtonClick,
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
	const isBranded = getPaymentRequestData( 'button' )?.is_branded;
	const brandedType = getPaymentRequestData( 'button' )?.branded_type;
	const isCustom = getPaymentRequestData( 'button' )?.is_custom;
	const { theme, height } = getPaymentRequestData( 'button' );

	const paymentRequestButtonStyle = {
		paymentRequestButton: {
			// Not implemented branded buttons default to Stripe's button.
			// Apple Pay buttons can also fall back to Stripe's button, as it's already branded.
			// Set button type to default or buy, depending on branded type, to avoid issues with Stripe.
			type: isBranded && 'long' === brandedType ? 'buy' : 'default',
			theme,
			height: height + 'px',
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
	const { stripe } = props;
	return (
		<Elements stripe={ stripe }>
			<PaymentRequestExpressComponent { ...props } />
		</Elements>
	);
};
