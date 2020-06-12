/**
 * External dependencies
 */
import {
	Elements,
	ElementsConsumer,
	CardElement,
} from '@stripe/react-stripe-js';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import generatePaymentMethod from './generate-payment-method.js';
import confirmCardPayment from './confirm-card-payment.js';

const WCPayFields = ( {
	api,
	stripe,
	elements,
	eventRegistration: {
		onPaymentProcessing,
		onCheckoutAfterProcessingWithSuccess,
	},
} ) => {
	// When it's time to process the payment, generate a Stripe payment method object.
	useEffect(
		() => onPaymentProcessing( () => {
			const paymentElements = {
				card: elements.getElement( CardElement ),
			};

			return generatePaymentMethod( stripe, paymentElements );
		} ),
	[ elements, stripe ] );

	// Once the server has completed payment processing, confirm the intent of necessary.
	useEffect(
		() => onCheckoutAfterProcessingWithSuccess(
			( {
				processingResponse: {
					paymentDetails,
				},
			} ) => (
				confirmCardPayment( api, paymentDetails )
			)
		),
	[ elements, stripe ] );

	const elementOptions = {
		hidePostalCode: true,
		classes: {
			base: 'wcpay-card-mounted',
		},
	};

	return <CardElement options={ elementOptions } />;
};

/**
 * Wraps WCPayFields within the necessary Stripe consumer components.
 *
 * @param {Object} props All props given by WooCommerce Blocks.
 * @returns {Object}     The wrapped React element.
 */
const ConsumableWCPayFields = ( { api, ...props } ) => (
	<Elements stripe={ api.getStripe() }>
		<ElementsConsumer>
			{ ( { elements, stripe } ) => (
				<WCPayFields api={ api } elements={ elements } stripe={ stripe } { ...props } />
			) }
		</ElementsConsumer>
	</Elements>
);

export default ConsumableWCPayFields;
