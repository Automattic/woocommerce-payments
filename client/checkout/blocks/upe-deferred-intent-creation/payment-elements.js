/**
 * Internal dependencies
 */
import { getAppearance } from 'wcpay/checkout/upe-styles';
import { getUPEConfig } from 'wcpay/utils/checkout';
import { useFingerprint } from '../hooks';
import { LoadableBlock } from 'wcpay/components/loadable';
import { Elements, PaymentElement, useElements } from '@stripe/react-stripe-js';
import {
	getPaymentMethods,
	// eslint-disable-next-line import/no-unresolved
} from '@woocommerce/blocks-registry';
import { useEffect, useState } from 'react';
import {
	createStripePaymentMethod,
	validateElements,
} from 'wcpay/checkout/classic/upe-deferred-intent-creation/stripe-checkout';
import { getTerms } from 'wcpay/checkout/utils/upe';
import confirmUPEPayment from '../confirm-upe-payment';

const WCPayUPEFields = ( {
	api,
	activePaymentMethod,
	testingInstructions,
	billing: { billingData },
	eventRegistration: {
		onPaymentProcessing,
		onCheckoutAfterProcessingWithSuccess,
	},
	emitResponse,
	paymentMethodId,
	upeMethods,
	paymentIntentId,
	paymentIntentSecret,
	errorMessage,
	shouldSavePayment,
	fingerprint,
} ) => {
	// const stripe = useStripe();
	const elements = useElements();
	// const [ isUPEComplete, setIsUPEComplete ] = useState( false );
	const [ selectedUPEPaymentType, setSelectedUPEPaymentType ] = useState(
		''
	);
	const [ paymentCountry, setPaymentCountry ] = useState( null );

	const paymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );
	const isTestMode = getUPEConfig( 'testMode' );
	const testingInstructionsIfAppropriate = isTestMode
		? testingInstructions
		: '';
	const gatewayConfig = getPaymentMethods()[ upeMethods[ paymentMethodId ] ];
	// const customerData = useCustomerData();

	useEffect( () => {
		const handlePaymentProcessing = async () => {
			if ( upeMethods[ paymentMethodId ] !== activePaymentMethod ) {
				return;
			}

			// if ( ! isUPEComplete ) {
			//     return {
			//         type: 'error',
			//         message: __(
			//             'Your payment information is incomplete.',
			//             'woocommerce-payments'
			//         ),
			//     };
			// }

			if ( errorMessage ) {
				return {
					type: 'error',
					message: errorMessage,
				};
			}

			if (
				gatewayConfig.supports.showSaveOption &&
				shouldSavePayment &&
				! paymentMethodsConfig[ selectedUPEPaymentType ].isReusable
			) {
				return {
					type: 'error',
					message:
						'This payment method cannot be saved for future use.',
				};
			}

			const fraudPreventionToken = document
				.querySelector( '#wcpay-fraud-prevention-token' )
				?.getAttribute( 'value' );

			await validateElements( elements );
			const paymentMethodObject = await createStripePaymentMethod(
				api,
				elements
			);

			return {
				type: 'success',
				meta: {
					paymentMethodData: {
						payment_method: paymentMethodId,
						'wcpay-payment-method':
							paymentMethodObject.paymentMethod.id,
						'wcpay-fraud-prevention-token': fraudPreventionToken,
						'wcpay-fingerprint': fingerprint,
					},
				},
			};
		};

		onPaymentProcessing( handlePaymentProcessing );
	}, [
		activePaymentMethod,
		api,
		elements,
		errorMessage,
		fingerprint,
		gatewayConfig,
		onPaymentProcessing,
		paymentMethodId,
		paymentMethodsConfig,
		selectedUPEPaymentType,
		shouldSavePayment,
		upeMethods,
	] );

	useEffect(
		() =>
			onCheckoutAfterProcessingWithSuccess(
				( { orderId, processingResponse: { paymentDetails } } ) => {
					async function updateIntent() {
						if ( api.handleDuplicatePayments( paymentDetails ) ) {
							return;
						}

						await api.updateIntent(
							paymentIntentId,
							orderId,
							shouldSavePayment ? 'yes' : 'no',
							selectedUPEPaymentType,
							paymentCountry,
							fingerprint
						);

						return confirmUPEPayment(
							api,
							paymentDetails.redirect_url,
							paymentDetails.payment_needed,
							paymentIntentSecret,
							elements,
							billingData,
							emitResponse
						);
					}

					return updateIntent();
				}
			),
		[
			api,
			billingData,
			fingerprint,
			elements,
			emitResponse,
			onCheckoutAfterProcessingWithSuccess,
			paymentCountry,
			paymentIntentId,
			paymentIntentSecret,
			selectedUPEPaymentType,
			shouldSavePayment,
		]
	);

	const elementOptions = {
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
		wallets: {
			applePay: 'never',
			googlePay: 'never',
		},
	};

	const showTerms =
		shouldSavePayment || getUPEConfig( 'cartContainsSubscription' )
			? 'always'
			: 'never';
	elementOptions.terms = getTerms( paymentMethodsConfig, showTerms );

	// Checks whether there are errors within a field, and saves them for later reporting.
	const upeOnChange = ( event ) => {
		// Update WC Blocks gateway config based on selected UPE payment method.
		const paymentType =
			'link' !== event.value.type ? event.value.type : 'card';
		gatewayConfig.supports.showSaveOption =
			paymentMethodsConfig[ paymentType ].showSaveOption;

		// setIsUPEComplete( event.complete );
		setSelectedUPEPaymentType( paymentType );
		setPaymentCountry( event.value.country );
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
				options={ elementOptions }
				onChange={ upeOnChange }
				className="wcpay-payment-element"
			/>
		</>
	);
};

const ConsumableWCPayFields = ( { api, ...props } ) => {
	const stripe = api.getStripe();
	const [ errorMessage, setErrorMessage ] = useState( null );
	const [ appearance, setAppearance ] = useState(
		getUPEConfig( 'wcBlocksUPEAppearance' )
	);
	const [ fingerprint, fingerprintErrorMessage ] = useFingerprint();
	const amount = Number( getUPEConfig( 'cartTotal' ) );
	const currency = getUPEConfig( 'currency' ).toLowerCase();

	useEffect( () => {
		async function generateUPEAppearance() {
			// Generate UPE input styles.
			const upeAppearance = getAppearance( true );
			await api.saveUPEAppearance( upeAppearance, 'true' );
			setAppearance( upeAppearance );
		}

		if ( ! appearance ) {
			generateUPEAppearance();
		}

		if ( fingerprintErrorMessage ) {
			setErrorMessage( fingerprintErrorMessage );
			// return;
		}

		// if ( ! fingerprint ) {
		// return;
		// }
	}, [ api, appearance, fingerprint, fingerprintErrorMessage ] );

	return (
		<LoadableBlock isLoading={ ! appearance } numLines={ 3 }>
			<Elements
				stripe={ stripe }
				options={ {
					mode: 1 > amount ? 'setup' : 'payment',
					amount: amount,
					currency: currency,
					paymentMethodCreation: 'manual',
					paymentMethodTypes: [ props.paymentMethodId ],
					appearance: appearance,
				} }
			>
				<WCPayUPEFields
					api={ api }
					errorMessage={ errorMessage }
					fingerprint={ fingerprint }
					{ ...props }
				/>
			</Elements>
		</LoadableBlock>
	);
};

export default ConsumableWCPayFields;
