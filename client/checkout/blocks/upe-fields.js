/**
 * External dependencies
 */
import {
	Elements,
	ElementsConsumer,
	PaymentElement,
} from '@stripe/react-stripe-js';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import confirmUPEPayment from './confirm-upe-payment.js';
import { getConfig } from 'utils/checkout';
import { PAYMENT_METHOD_NAME_CARD } from '../constants.js';

const WCPayUPEFields = ( {
	api,
	activePaymentMethod,
	stripe,
	elements,
	billing: { billingData },
	eventRegistration: {
		onPaymentProcessing,
		onCheckoutAfterProcessingWithSuccess,
	},
	emitResponse,
} ) => {
	const [ paymentIntentId, setPaymentIntentId ] = useState( null );
	const [ clientSecret, setClientSecret ] = useState( null );
	const [ hasRequestedIntent, setHasRequestedIntent ] = useState( false );
	const [ isUPEComplete, setIsUPEComplete ] = useState( false );

	const testMode = getConfig( 'testMode' );
	const testCopy = (
		<p>
			<strong>Test mode:</strong> use the test VISA card 4242424242424242
			with any expiry date and CVC.
		</p>
	);

	useEffect( () => {
		if ( paymentIntentId || hasRequestedIntent ) {
			return;
		}

		async function createIntent() {
			const response = await api.createIntent();
			setPaymentIntentId( response.id );
			setClientSecret( response.client_secret );
		}
		setHasRequestedIntent( true );

		createIntent();
	}, [ paymentIntentId, hasRequestedIntent, api ] );

	// When it's time to process the payment, generate a Stripe payment method object.
	useEffect(
		() =>
			onPaymentProcessing( () => {
				if ( PAYMENT_METHOD_NAME_CARD !== activePaymentMethod ) {
					return;
				}

				if ( ! isUPEComplete ) {
					return {
						type: 'error',
						message: 'Your payment information is incomplete.',
					};
				}
			} ),
		// not sure if we need to disable this, but kept it as-is to ensure nothing breaks. Please consider passing all the deps.
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ activePaymentMethod, isUPEComplete ]
	);

	// Once the server has completed payment processing, confirm the intent if necessary.
	useEffect(
		() =>
			onCheckoutAfterProcessingWithSuccess(
				( { orderId, processingResponse: { paymentDetails } } ) => {
					async function updateIntent() {
						await api.updateIntent(
							paymentIntentId,
							orderId,
							false
						);

						const paymentElement = elements.getElement(
							PaymentElement
						);

						return confirmUPEPayment(
							api,
							paymentDetails.redirect_url,
							paymentElement,
							billingData,
							emitResponse
						);
					}

					updateIntent();
				}
			),
		// not sure if we need to disable this, but kept it as-is to ensure nothing breaks. Please consider passing all the deps.
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ elements, stripe, api, paymentIntentId ]
	);

	// Checks whether there are errors within a field, and saves them for later reporting.
	const upeOnChange = ( event ) => {
		setIsUPEComplete( event.complete );
	};

	const elementOptions = {
		clientSecret,
		classes: {
			base: 'wcpay-card-mounted',
		},
		fields: {
			billingDetails: {
				name: 'never',
				email: 'never',
				phone: 'never',
				address: {
					country: 'never',
					line1: 'never',
					line2: 'never',
					city: 'never',
					state: 'never',
					postalCode: 'never',
				},
			},
		},
	};

	if ( ! clientSecret ) {
		return null;
	}

	return (
		<>
			{ testMode ? testCopy : '' }
			<PaymentElement
				options={ elementOptions }
				onChange={ upeOnChange }
			/>
		</>
	);
};

/**
 * Wraps WCPayFields within the necessary Stripe consumer components.
 *
 * @param {Object} props All props given by WooCommerce Blocks.
 * @return {Object}     The wrapped React element.
 */
const ConsumableWCPayFields = ( { api, ...props } ) => (
	<Elements stripe={ api.getStripe() }>
		<ElementsConsumer>
			{ ( { elements, stripe } ) => (
				<WCPayUPEFields
					api={ api }
					elements={ elements }
					stripe={ stripe }
					{ ...props }
				/>
			) }
		</ElementsConsumer>
	</Elements>
);

export default ConsumableWCPayFields;
