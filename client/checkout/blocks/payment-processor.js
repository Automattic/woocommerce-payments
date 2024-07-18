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
import { useEffect, useRef } from 'react';

/**
 * Internal dependencies
 */
import { usePaymentCompleteHandler, usePaymentFailHandler } from './hooks';
import {
	getStripeElementOptions,
	blocksShowLinkButtonHandler,
	getBlocksEmailValue,
	isLinkEnabled,
} from 'wcpay/checkout/utils/upe';
import { useCustomerData } from './utils';
import enableStripeLinkPaymentMethod from 'wcpay/checkout/stripe-link';
import { getUPEConfig } from 'wcpay/utils/checkout';
import { validateElements } from 'wcpay/checkout/classic/payment-processing';

const getBillingDetails = ( billingData ) => {
	return {
		name: `${ billingData.first_name } ${ billingData.last_name }`.trim(),
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
	return window.wcpayFraudPreventionToken ?? '';
};

const noop = () => null;

const PaymentProcessor = ( {
	api,
	activePaymentMethod,
	testingInstructions,
	eventRegistration: { onPaymentSetup, onCheckoutSuccess, onCheckoutFail },
	emitResponse,
	paymentMethodId,
	upeMethods,
	errorMessage,
	shouldSavePayment,
	fingerprint,
	onLoadError = noop,
} ) => {
	const stripe = useStripe();
	const elements = useElements();
	const hasLoadErrorRef = useRef( false );

	const paymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );
	const isTestMode = getUPEConfig( 'testMode' );
	const gatewayConfig = getPaymentMethods()[ upeMethods[ paymentMethodId ] ];
	const {
		billingAddress: billingData,
		setShippingAddress,
		setBillingAddress,
	} = useCustomerData();

	useEffect( () => {
		if ( isLinkEnabled( paymentMethodsConfig ) ) {
			enableStripeLinkPaymentMethod( {
				api: api,
				elements: elements,
				emailId: 'email',
				onAutofill: ( billingAddress, shippingAddress ) => {
					// in some cases (e.g.: customer doesn't select the payment method in the Link modal), the billing address is empty.
					if ( billingAddress ) {
						// setting the country first, in case the "state"/"county"/"province"
						// select changes from a select to a text field (or vice-versa).
						setBillingAddress( {
							country: billingAddress.country,
						} );
						// after the country, we can safely set the other fields
						setBillingAddress( {
							...billingAddress,
						} );
					}

					// in some cases (e.g.: customer doesn't select the shipping address method in the Link modal),
					// the shipping address is empty.
					if ( shippingAddress ) {
						// setting the country first, in case the "state"/"county"/"province"
						// select changes from a select to a text field (or vice-versa).
						setShippingAddress( {
							country: shippingAddress.country,
						} );
						// after the country, we can safely set the other fields
						setShippingAddress( {
							...shippingAddress,
						} );
					}

					// after all the above, we can now set the email field by getting its value from the DOM.
					setBillingAddress( {
						email: getBlocksEmailValue(),
					} );
					setShippingAddress( {
						email: getBlocksEmailValue(),
					} );
				},
				onButtonShow: blocksShowLinkButtonHandler,
			} );
		}
	}, [
		api,
		elements,
		paymentMethodsConfig,
		setBillingAddress,
		setShippingAddress,
	] );

	useEffect(
		() =>
			onPaymentSetup( () => {
				async function handlePaymentProcessing() {
					if (
						upeMethods[ paymentMethodId ] !== activePaymentMethod
					) {
						return;
					}

					if ( hasLoadErrorRef.current ) {
						return {
							type: 'error',
							message: __(
								'Invalid or missing payment details. Please ensure the provided payment method is correctly entered.',
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
						! paymentMethodsConfig[ paymentMethodId ].isReusable
					) {
						return {
							type: 'error',
							message: __(
								'This payment method cannot be saved for future use.',
								'woocommerce-payments'
							),
						};
					}

					try {
						await validateElements( elements );
					} catch ( e ) {
						return {
							type: 'error',
							message: e.message,
						};
					}

					const result = await api
						.getStripeForUPE( paymentMethodId )
						.createPaymentMethod( {
							elements,
							params: {
								billing_details: getBillingDetails(
									billingData
								),
							},
						} );

					if ( result.error ) {
						return {
							type: 'error',
							message: result.error.message,
						};
					}

					return {
						type: 'success',
						meta: {
							paymentMethodData: {
								payment_method: upeMethods[ paymentMethodId ],
								'wcpay-payment-method': result.paymentMethod.id,
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
			shouldSavePayment,
			upeMethods,
			errorMessage,
			onPaymentSetup,
			billingData,
		]
	);

	usePaymentCompleteHandler(
		api,
		stripe,
		elements,
		onCheckoutSuccess,
		emitResponse,
		shouldSavePayment
	);

	usePaymentFailHandler( onCheckoutFail, emitResponse );

	const setHasLoadError = ( event ) => {
		hasLoadErrorRef.current = true;
		onLoadError( event );
	};

	return (
		<>
			{ isTestMode && (
				<p
					className="content"
					dangerouslySetInnerHTML={ {
						__html: testingInstructions,
					} }
				/>
			) }
			<PaymentElement
				options={ getStripeElementOptions(
					shouldSavePayment,
					paymentMethodsConfig
				) }
				onLoadError={ setHasLoadError }
				className="wcpay-payment-element"
			/>
		</>
	);
};

export default PaymentProcessor;
