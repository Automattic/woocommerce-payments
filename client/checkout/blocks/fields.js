/**
 * External dependencies
 */
import {
	Elements,
	ElementsConsumer,
	CardElement,
} from '@stripe/react-stripe-js';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './style.scss';
import generatePaymentMethod from './generate-payment-method.js';
import { PAYMENT_METHOD_NAME_CARD } from '../constants.js';
import { usePaymentCompleteHandler } from './hooks';

const WCPayFields = ( {
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
	const [ errorMessage, setErrorMessage ] = useState( null );

	// When it's time to process the payment, generate a Stripe payment method object.
	useEffect(
		() =>
			onPaymentProcessing( () => {
				if ( PAYMENT_METHOD_NAME_CARD !== activePaymentMethod ) {
					return;
				}

				if ( errorMessage ) {
					return {
						type: 'error',
						message: errorMessage,
					};
				}

				const cardElement = elements.getElement( CardElement );
				const paymentElements = {
					type: 'card',
					card: cardElement,
				};

				return generatePaymentMethod(
					api,
					paymentElements,
					billingData
				);
			} ),
		// not sure if we need to disable this, but kept it as-is to ensure nothing breaks. Please consider passing all the deps.
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ elements, stripe, activePaymentMethod, billingData ]
	);

	// Once the server has completed payment processing, confirm the intent of necessary.
	usePaymentCompleteHandler(
		api,
		stripe,
		elements,
		onCheckoutAfterProcessingWithSuccess,
		emitResponse,
		shouldSavePayment
	);

	// Checks whether there are errors within a field, and saves them for later reporting.
	const checkForErrors = ( { error } ) => {
		setErrorMessage( error ? error.message : null );
	};

	const elementOptions = {
		hidePostalCode: true,
		classes: {
			base: 'wcpay-card-mounted',
		},
	};

	return (
		<div className="wc-block-gateway-container wc-inline-card-element">
			<CardElement
				options={ elementOptions }
				onChange={ checkForErrors }
			/>
		</div>
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
				<WCPayFields
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
