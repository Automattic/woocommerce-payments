/**
 * External dependencies
 */
import { Elements, PaymentRequestButtonElement } from '@stripe/react-stripe-js';

/**
 * Internal dependencies
 */
import { useInitialization } from './use-initialization';
import { GooglePayButton } from './branded-buttons';
import { CustomButton } from './custom-button';
import { getPaymentRequestData, shouldUseGooglePayBrand } from '../utils';

/**
 * PaymentRequestExpressComponent
 *
 * @param {Object} props Incoming props.
 *
 * @return {ReactNode} Payment Request button component.
 */
const PaymentRequestExpressComponent = ( {
	api,
	setExpressPaymentError,
	onClick,
	onClose,
} ) => {
	// TODO: Don't display custom button when result.requestType
	// is `apple_pay` or `google_pay`.
	// TODO: Add loading indicator when isProcessing.
	const {
		paymentRequest,
		// paymentRequestType,
		// isProcessing,
		canMakePayment,
		onButtonClick,
	} = useInitialization( {
		api,
		setExpressPaymentError,
		onClick,
		onClose,
	} );

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
				style: paymentRequestButtonStyle,
				paymentRequest,
			} }
		/>
	);
};

/**
 * PaymentRequestExpress express payment method component.
 *
 * @param {Object} props PaymentMethodProps.
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
