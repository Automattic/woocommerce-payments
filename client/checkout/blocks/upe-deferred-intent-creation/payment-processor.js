/**
 * External dependencies
 */
import {
	PaymentElement,
	useElements,
	useStripe,
} from '@stripe/react-stripe-js';
import {
	getPaymentMethods,
	// eslint-disable-next-line import/no-unresolved
} from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from 'react';

/**
 * Internal dependencies
 */
import { usePaymentCompleteHandler } from '../hooks';
import {
	getStripeElementOptions,
	useCustomerData,
} from 'wcpay/checkout/utils/upe';
import { getUPEConfig } from 'wcpay/utils/checkout';
import { validateElements } from 'wcpay/checkout/classic/upe-deferred-intent-creation/payment-processing';

const getBillingDetails = ( billingData ) => {
	return {
		name: ( billingData.first_name + ' ' + billingData.last_name ).trim(),
		email: billingData.email,
		phone: billingData.phone,
		address: {
			city: billingData.city,
			country: billingData.country,

			line1: billingData.address_1,
			line2: billingData.address_2,
			postal_code: billingData.postcode,
			state: billingData.state,
		},
	};
};

const getFraudPreventionToken = () => {
	return document
		.querySelector( '#wcpay-fraud-prevention-token' )
		?.getAttribute( 'value' );
};

const PaymentProcessor = ( {
	api,
	activePaymentMethod,
	testingInstructions,
	eventRegistration: { onPaymentSetup, onCheckoutAfterProcessingWithSuccess },
	emitResponse,
	paymentMethodId,
	upeMethods,
	errorMessage,
	shouldSavePayment,
	fingerprint,
} ) => {
	const stripe = useStripe();
	const elements = useElements();
	const [ isUPEComplete, setIsUPEComplete ] = useState( false );
	const [ selectedUPEPaymentType, setSelectedUPEPaymentType ] = useState(
		''
	);

	const paymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );
	const isTestMode = getUPEConfig( 'testMode' );
	const testingInstructionsIfAppropriate = isTestMode
		? testingInstructions
		: '';
	const gatewayConfig = getPaymentMethods()[ upeMethods[ paymentMethodId ] ];
	const billingData = useCustomerData().billingAddress;

	useEffect(
		() =>
			onPaymentSetup( () => {
				async function handlePaymentProcessing() {
					if (
						upeMethods[ paymentMethodId ] !== activePaymentMethod
					) {
						return;
					}

					if ( ! isUPEComplete ) {
						return {
							type: 'error',
							message: __(
								'Your payment information is incomplete.',
								'woocommerce-payments'
							),
						};
					}

					if ( errorMessage ) {
						return {
							type: 'error',
							message: errorMessage,
						};
					}

					if (
						gatewayConfig.supports.showSaveOption &&
						shouldSavePayment &&
						! paymentMethodsConfig[ selectedUPEPaymentType ]
							.isReusable
					) {
						return {
							type: 'error',
							message:
								'This payment method cannot be saved for future use.',
						};
					}

					await validateElements( elements );

					const paymentMethodObject = await api
						.getStripe()
						.createPaymentMethod( {
							elements,
							params: {
								billing_details: getBillingDetails(
									billingData
								),
							},
						} );

					return {
						type: 'success',
						meta: {
							paymentMethodData: {
								payment_method: paymentMethodId,
								'wcpay-payment-method':
									paymentMethodObject.paymentMethod.id,
								'wcpay-fraud-prevention-token': getFraudPreventionToken(),
								'wcpay-fingerprint': fingerprint,
							},
						},
					};
				}
				return handlePaymentProcessing();
			} ),
		[
			activePaymentMethod,
			api,
			elements,
			fingerprint,
			gatewayConfig,
			paymentMethodId,
			paymentMethodsConfig,
			selectedUPEPaymentType,
			shouldSavePayment,
			upeMethods,
			errorMessage,
			onPaymentSetup,
			billingData,
			isUPEComplete,
		]
	);

	usePaymentCompleteHandler(
		api,
		stripe,
		elements,
		onCheckoutAfterProcessingWithSuccess,
		emitResponse,
		shouldSavePayment
	);

	// Checks whether there are errors within a field, and saves them for later reporting.
	const upeOnChange = ( event ) => {
		// Update WC Blocks gateway config based on selected UPE payment method.
		const paymentType =
			'link' !== event.value.type ? event.value.type : 'card';
		gatewayConfig.supports.showSaveOption =
			paymentMethodsConfig[ paymentType ].showSaveOption;

		setIsUPEComplete( event.complete );
		setSelectedUPEPaymentType( paymentType );
	};

	return (
		<>
			<p
				className="content"
				dangerouslySetInnerHTML={ {
					__html: testingInstructionsIfAppropriate,
				} }
			></p>
			<PaymentElement
				options={ getStripeElementOptions(
					shouldSavePayment,
					paymentMethodsConfig
				) }
				onChange={ upeOnChange }
				className="wcpay-payment-element"
			/>
		</>
	);
};

export default PaymentProcessor;
