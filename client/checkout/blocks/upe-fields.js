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
import { payment } from '@wordpress/icons/build-types';

const WCPayUPEFields = ( {
	api,
	activePaymentMethod,
	stripe,
	elements,
	billing: { billingData },
	eventRegistration: { onCheckoutAfterProcessingWithSuccess },
	emitResponse,
} ) => {
	const [ errorMessage, setErrorMessage ] = useState( null );
	const [ paymentIntentId, setPaymentIntentId ] = useState( null );
	const [ clientSecret, setClientSecret ] = useState( null );
	const [ isFetchingIntent, setIsFetchingIntent ] = useState( false );

	const testMode = getConfig( 'testMode' );
	let testCopy = '';
	if ( testMode ) {
		testCopy = (
			<p>
				<strong>Test mode:</strong> use the test VISA card
				4242424242424242 with any expiry date and CVC.
			</p>
		);
	}

	useEffect( () => {
		if ( paymentIntentId || isFetchingIntent ) {
			return;
		}

		async function createIntent() {
			const response = await api.createIntent();
			setIsFetchingIntent( false );
			setPaymentIntentId( response.id );
			setClientSecret( response.client_secret );
		}
		setIsFetchingIntent( true );

		createIntent();
	}, [ paymentIntentId, isFetchingIntent, api ] );

	// Once the server has completed payment processing, confirm the intent of necessary.
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

						confirmUPEPayment(
							api,
							paymentDetails,
							paymentElement,
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
	const checkForErrors = ( { error } ) => {
		setErrorMessage( error ? error.message : null );
	};

	const elementOptions = {
		clientSecret,
		classes: {
			base: 'wcpay-card-mounted',
		},
	};

	if ( clientSecret ) {
		return (
			<>
				<input
					type="hidden"
					name="wc_payment_intent_id"
					value={ paymentIntentId }
				/>
				{ testCopy }
				<PaymentElement
					options={ elementOptions }
					onChange={ checkForErrors }
				/>
			</>
		);
	}

	return <h1>Loading...</h1>;
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
