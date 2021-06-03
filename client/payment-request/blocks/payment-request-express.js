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
	billing,
	shippingData,
	setExpressPaymentError,
	onClick,
	onClose,
} ) => {
	// TODO: Don't display custom button when result.requestType
	// is `apple_pay` or `google_pay`.
	const {
		paymentRequest,
		// paymentRequestType,
		onButtonClick,
	} = useInitialization( {
		api,
		billing,
		shippingData,
		setExpressPaymentError,
		onClick,
		onClose,
	} );

	// TODO: migrate button data
	const { type, theme, height } = getPaymentRequestData( 'button' );
	const isBranded = getPaymentRequestData( 'button' )?.is_branded;
	const brandedType = getPaymentRequestData( 'button' )?.branded_type;
	const isCustom = getPaymentRequestData( 'button' )?.is_custom;

	const paymentRequestButtonStyle = {
		paymentRequestButton: {
			type,
			theme,
			height: height + 'px',
		},
	};

	if ( ! paymentRequest ) {
		return null;
	}

	// This can be removed once the `is_grouped_settings` flag returns `true` and the code is cleaned up.
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

	// This can be removed once the `is_grouped_settings` flag returns `true` and the code is cleaned up.
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

	// This can be removed once the `is_grouped_settings` flag returns `true` and the code is cleaned up.
	if ( isBranded ) {
		// Not implemented branded buttons default to Stripe's button.
		// Apple Pay buttons can also fall back to Stripe's button, as it's already branded.
		// Set button type to default or buy, depending on branded type, to avoid issues with Stripe.
		paymentRequestButtonStyle.paymentRequestButton.type =
			'long' === brandedType ? 'buy' : 'default';
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
