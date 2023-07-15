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
	blocksShowLinkButtonHandler,
	getBlocksEmailValue,
} from 'wcpay/checkout/utils/upe';
import enableStripeLinkPaymentMethod from 'wcpay/checkout/stripe-link';
import { getUPEConfig } from 'wcpay/utils/checkout';
import { validateElements } from 'wcpay/checkout/classic/upe-deferred-intent-creation/payment-processing';
import {
	BLOCKS_SHIPPING_ADDRESS_FIELDS,
	BLOCKS_BILLING_ADDRESS_FIELDS,
} from '../../constants';

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
	const [ isPaymentElementComplete, setIsPaymentElementComplete ] = useState(
		false
	);

	const paymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );
	const isTestMode = getUPEConfig( 'testMode' );
	const testingInstructionsIfAppropriate = isTestMode
		? testingInstructions
		: '';
	const gatewayConfig = getPaymentMethods()[ upeMethods[ paymentMethodId ] ];
	const customerData = useCustomerData();
	const billingData = customerData.billingAddress;

	useEffect( () => {
		if (
			paymentMethodsConfig.link !== undefined &&
			paymentMethodsConfig.card !== undefined
		) {
			enableStripeLinkPaymentMethod( {
				api: api,
				elements: elements,
				emailId: 'email',
				fill_field_method: ( address, nodeId, key ) => {
					const setAddress =
						BLOCKS_SHIPPING_ADDRESS_FIELDS[ key ] === nodeId
							? customerData.setShippingAddress
							: customerData.setBillingData ||
							  customerData.setBillingAddress;
					const customerAddress =
						BLOCKS_SHIPPING_ADDRESS_FIELDS[ key ] === nodeId
							? customerData.shippingAddress
							: customerData.billingData ||
							  customerData.billingAddress;

					if ( 'line1' === key ) {
						customerAddress.address_1 = address.address[ key ];
					} else if ( 'line2' === key ) {
						customerAddress.address_2 = address.address[ key ];
					} else if ( 'postal_code' === key ) {
						customerAddress.postcode = address.address[ key ];
					} else {
						customerAddress[ key ] = address.address[ key ];
					}

					setAddress( customerAddress );

					if ( customerData.billingData ) {
						customerData.billingData.email = getBlocksEmailValue();
						customerData.setBillingData( customerData.billingData );
					} else {
						customerData.billingAddress.email = getBlocksEmailValue();
						customerData.setBillingAddress(
							customerData.billingAddress
						);
					}
				},
				show_button: blocksShowLinkButtonHandler,
				complete_shipping: () => {
					return (
						null !== document.getElementById( 'shipping-address_1' )
					);
				},
				shipping_fields: BLOCKS_SHIPPING_ADDRESS_FIELDS,
				billing_fields: BLOCKS_BILLING_ADDRESS_FIELDS,
				complete_billing: () => {
					return (
						null !== document.getElementById( 'billing-address_1' )
					);
				},
			} );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ elements ] );

	useEffect(
		() =>
			onPaymentSetup( () => {
				async function handlePaymentProcessing() {
					if (
						upeMethods[ paymentMethodId ] !== activePaymentMethod
					) {
						return;
					}

					if ( ! isPaymentElementComplete ) {
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
						! paymentMethodsConfig[ paymentMethodId ].isReusable
					) {
						return {
							type: 'error',
							message:
								'This payment method cannot be saved for future use.',
						};
					}

					await validateElements( elements );

					const paymentMethodObject = await api
						.getStripeForUPE( paymentMethodId )
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
			shouldSavePayment,
			upeMethods,
			errorMessage,
			onPaymentSetup,
			billingData,
			isPaymentElementComplete,
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

	const updatePaymentElementCompletionStatus = ( event ) => {
		setIsPaymentElementComplete( event.complete );
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
				onChange={ updatePaymentElementCompletionStatus }
				className="wcpay-payment-element"
			/>
		</>
	);
};

export default PaymentProcessor;
