/* global jQuery */

/**
 * External dependencies
 */
import {
	Elements,
	useStripe,
	useElements,
	PaymentElement,
} from '@stripe/react-stripe-js';
import {
	getPaymentMethods,
	// eslint-disable-next-line import/no-unresolved
} from '@woocommerce/blocks-registry';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './style.scss';
import confirmUPEPayment from './confirm-upe-payment.js';
import { getConfig } from 'utils/checkout';
import { getStripeElementOptions, useCustomerData } from '../utils/upe';
import { decryptClientSecret } from '../utils/encryption';
import { PAYMENT_METHOD_NAME_CARD } from '../constants.js';
import enableStripeLinkPaymentMethod from 'wcpay/checkout/stripe-link';
import { getAppearance, getFontRulesFromPage } from '../upe-styles';
import { useFingerprint } from './hooks';
import {
	BLOCKS_SHIPPING_ADDRESS_FIELDS,
	BLOCKS_BILLING_ADDRESS_FIELDS,
} from '../constants';

const WCPayUPEFields = ( {
	api,
	activePaymentMethod,
	billing: { billingData },
	eventRegistration: {
		onPaymentProcessing,
		onCheckoutAfterProcessingWithSuccess,
	},
	emitResponse,
	paymentIntentId,
	paymentIntentSecret,
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
	const [ paymentCountry, setPaymentCountry ] = useState( null );

	const paymentMethodsConfig = getConfig( 'paymentMethodsConfig' );
	const testMode = getConfig( 'testMode' );
	const testCopy = (
		<p>
			<strong>Test mode:</strong> use the test VISA card 4242424242424242
			with any expiry date and CVC.
		</p>
	);

	const gatewayConfig = getPaymentMethods()[ PAYMENT_METHOD_NAME_CARD ];
	const customerData = useCustomerData();

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

					function getEmail() {
						return document.getElementById( 'email' ).value;
					}

					if ( customerData.billingData ) {
						customerData.billingData.email = getEmail();
						customerData.setBillingData( customerData.billingData );
					} else {
						customerData.billingAddress.email = getEmail();
						customerData.setBillingAddress(
							customerData.billingAddress
						);
					}
				},
				show_button: ( linkAutofill ) => {
					jQuery( '#email' )
						.parent()
						.append(
							'<button class="wcpay-stripelink-modal-trigger"></button>'
						);
					if ( '' !== jQuery( '#email' ).val() ) {
						jQuery( '.wcpay-stripelink-modal-trigger' ).show();
					}

					//Handle StripeLink button click.
					jQuery( '.wcpay-stripelink-modal-trigger' ).on(
						'click',
						( event ) => {
							event.preventDefault();
							// Trigger modal.
							linkAutofill.launch( {
								email: jQuery( '#email' ).val(),
							} );
						}
					);
				},
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
					! paymentMethodsConfig[ selectedUPEPaymentType ].isReusable
				) {
					return {
						type: 'error',
						message: __(
							'This payment method can not be saved for future use.',
							'woocommerce-payments'
						),
					};
				}

				const fraudPreventionToken = document
					.querySelector( '#wcpay-fraud-prevention-token' )
					?.getAttribute( 'value' );

				return {
					type: 'success',
					meta: {
						paymentMethodData: {
							paymentMethod: PAYMENT_METHOD_NAME_CARD,
							wc_payment_intent_id: paymentIntentId,
							wcpay_selected_upe_payment_type: selectedUPEPaymentType,
							'wcpay-fraud-prevention-token':
								fraudPreventionToken ?? '',
							'wcpay-fingerprint': fingerprint,
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
			fingerprint,
		]
	);

	// Once the server has completed payment processing, confirm the intent if necessary.
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
							emitResponse,
							selectedUPEPaymentType
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
		// Update WC Blocks gateway config based on selected UPE payment method.
		gatewayConfig.supports.showSaveOption =
			paymentMethodsConfig[ event.value.type ].showSaveOption;

		setIsUPEComplete( event.complete );
		setSelectedUPEPaymentType( event.value.type );
		setPaymentCountry( event.value.country );
	};

	return (
		<>
			{ testMode ? testCopy : '' }
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

/**
 * Wraps WCPayFields within the necessary Stripe consumer components.
 *
 * @param {Object} props All props given by WooCommerce Blocks.
 * @return {Object}     The wrapped React element.
 */
const ConsumableWCPayFields = ( { api, ...props } ) => {
	const stripe = api.getStripe();
	const [ paymentIntentId, setPaymentIntentId ] = useState( null );
	const [ clientSecret, setClientSecret ] = useState( null );
	const [ hasRequestedIntent, setHasRequestedIntent ] = useState( false );
	const [ errorMessage, setErrorMessage ] = useState( null );
	const [ appearance, setAppearance ] = useState(
		getConfig( 'wcBlocksUPEAppearance' )
	);
	const [ fontRules ] = useState( getFontRulesFromPage() );
	const [ fingerprint, fingerprintErrorMessage ] = useFingerprint();

	useEffect( () => {
		async function generateUPEAppearance() {
			// Generate UPE input styles.
			const upeAppearance = getAppearance( true );
			await api.saveUPEAppearance( upeAppearance, 'true' );

			// Update appearance state
			setAppearance( upeAppearance );
		}
		if ( ! appearance ) {
			generateUPEAppearance();
		}

		if ( fingerprintErrorMessage ) {
			setErrorMessage( fingerprintErrorMessage );
			return;
		}

		if ( paymentIntentId || hasRequestedIntent || ! fingerprint ) {
			return;
		}

		async function createIntent() {
			try {
				const response = await api.createIntent( fingerprint );
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
	}, [
		paymentIntentId,
		hasRequestedIntent,
		api,
		errorMessage,
		appearance,
		fingerprint,
		fingerprintErrorMessage,
	] );

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
		<Elements
			stripe={ stripe }
			options={ {
				clientSecret: decryptClientSecret( clientSecret ),
				appearance,
				fonts: fontRules,
				loader: 'never',
			} }
		>
			<WCPayUPEFields
				api={ api }
				paymentIntentId={ paymentIntentId }
				paymentIntentSecret={ clientSecret }
				errorMessage={ errorMessage }
				fingerprint={ fingerprint }
				{ ...props }
			/>
		</Elements>
	);
};

export default ConsumableWCPayFields;
