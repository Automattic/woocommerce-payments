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
import './style.scss';
import confirmUPEPayment from './confirm-upe-payment.js';
import { getConfig } from 'utils/checkout';
import { getTerms } from '../utils/upe';
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
	shouldSavePayment,
} ) => {
	const [ paymentIntentId, setPaymentIntentId ] = useState( null );
	const [ clientSecret, setClientSecret ] = useState( null );
	const [ hasRequestedIntent, setHasRequestedIntent ] = useState( false );
	const [ isUPEComplete, setIsUPEComplete ] = useState( false );
	const [ errorMessage, setErrorMessage ] = useState( null );
	const [ selectedUPEPaymentType, setSelectedUPEPaymentType ] = useState(
		''
	);
	const [ paymentCountry, setPaymentCountry ] = useState( null );

	const paymentMethodsConfig = getConfig( 'paymentMethodsConfig' );
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
			try {
				const response = await api.createIntent();
				setPaymentIntentId( response.id );
				setClientSecret( response.client_secret );
			} catch ( error ) {
				setErrorMessage(
					error.message
						? error.message
						: 'There was an error loading the payment gateway.'
				);
			}
		}
		setHasRequestedIntent( true );
		createIntent();
	}, [ paymentIntentId, hasRequestedIntent, api, errorMessage ] );

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

				if ( errorMessage ) {
					return {
						type: 'error',
						message: errorMessage,
					};
				}

				if (
					shouldSavePayment &&
					! paymentMethodsConfig[ selectedUPEPaymentType ].isReusable
				) {
					return {
						type: 'error',
						message:
							'This payment method can not be saved for future use.',
					};
				}

				return {
					type: 'success',
					meta: {
						paymentMethodData: {
							paymentMethod: PAYMENT_METHOD_NAME_CARD,
							wc_payment_intent_id: paymentIntentId,
						},
					},
				};
			} ),
		// not sure if we need to disable this, but kept it as-is to ensure nothing breaks. Please consider passing all the deps.
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[
			activePaymentMethod,
			isUPEComplete,
			selectedUPEPaymentType,
			shouldSavePayment,
		]
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
							shouldSavePayment ? 'yes' : 'no',
							selectedUPEPaymentType,
							paymentCountry
						);

						const paymentElement = elements.getElement(
							PaymentElement
						);

						return confirmUPEPayment(
							api,
							paymentDetails.redirect_url,
							paymentDetails.payment_needed,
							paymentElement,
							billingData,
							emitResponse
						);
					}

					return updateIntent();
				}
			),
		// not sure if we need to disable this, but kept it as-is to ensure nothing breaks. Please consider passing all the deps.
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[
			elements,
			stripe,
			api,
			paymentIntentId,
			shouldSavePayment,
			selectedUPEPaymentType,
			paymentCountry,
		]
	);

	// Checks whether there are errors within a field, and saves them for later reporting.
	const upeOnChange = ( event ) => {
		setIsUPEComplete( event.complete );
		setSelectedUPEPaymentType( event.value.type );
		setPaymentCountry( event.value.country );
	};

	const elementOptions = {
		clientSecret,
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

	const showTerms =
		shouldSavePayment || getConfig( 'cartContainsSubscription' )
			? 'always'
			: 'never';
	elementOptions.terms = getTerms( paymentMethodsConfig, showTerms );

	const appearance = getConfig( 'upeAppearance' );
	if ( appearance ) {
		elementOptions.appearance = appearance;
	}

	if ( ! clientSecret ) {
		if ( errorMessage ) {
			return (
				<div className="woocommerce-error">
					<div className="components-notice__content">
						{ errorMessage }
					</div>
				</div>
			);
		}

		return null;
	}

	return (
		<>
			{ testMode ? testCopy : '' }
			<PaymentElement
				options={ elementOptions }
				onChange={ upeOnChange }
				className="wcpay-payment-element"
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
