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

/**
 * Internal dependencies
 */
import './style.scss';
import confirmUPEPayment from './confirm-upe-payment.js';
import { getConfig } from 'utils/checkout';
import { getTerms } from '../utils/upe';
import { PAYMENT_METHOD_NAME_CARD, WC_STORE_CART } from '../constants.js';
import enableStripeLinkPaymentMethod from 'wcpay/checkout/stripe-link';
import { useDispatch, useSelect } from '@wordpress/data';
import { getAppearance, getFontRulesFromPage } from '../upe-styles';

const useCustomerData = () => {
	const { customerData, isInitialized } = useSelect( ( select ) => {
		const store = select( WC_STORE_CART );
		return {
			customerData: store.getCustomerData(),
			isInitialized: store.hasFinishedResolution( 'getCartData' ),
		};
	} );
	const { setShippingAddress, setBillingData } = useDispatch( WC_STORE_CART );

	return {
		isInitialized,
		billingData: customerData.billingData,
		shippingAddress: customerData.shippingAddress,
		setBillingData,
		setShippingAddress,
	};
};

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
	errorMessage,
	shouldSavePayment,
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
			const shippingAddressFields = {
				line1: 'shipping-address_1',
				line2: 'shipping-address_2',
				city: 'shipping-city',
				state: 'components-form-token-input-1',
				postal_code: 'shipping-postcode',
				country: 'components-form-token-input-0',
			};
			const billingAddressFields = {
				line1: 'billing-address_1',
				line2: 'billing-address_2',
				city: 'billing-city',
				state: 'components-form-token-input-3',
				postal_code: 'billing-postcode',
				country: 'components-form-token-input-2',
			};

			enableStripeLinkPaymentMethod( {
				api: api,
				elements: elements,
				emailId: 'email',
				fill_field_method: ( address, nodeId, key ) => {
					const setAddress =
						shippingAddressFields[ key ] === nodeId
							? customerData.setShippingAddress
							: customerData.setBillingData;
					const customerAddress =
						shippingAddressFields[ key ] === nodeId
							? customerData.shippingAddress
							: customerData.billingData;

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

					customerData.billingData.email = getEmail();
					customerData.setBillingData( customerData.billingData );
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
				complete_shipping: true,
				shipping_fields: shippingAddressFields,
				billing_fields: billingAddressFields,
				complete_billing: () => {
					return ! document.getElementById( 'checkbox-control-0' )
						.checked;
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
					gatewayConfig.supports.showSaveOption &&
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
							wcpay_selected_upe_payment_type: selectedUPEPaymentType,
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

						return confirmUPEPayment(
							api,
							paymentDetails.redirect_url,
							paymentDetails.payment_needed,
							elements,
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
		// Update WC Blocks gateway config based on selected UPE payment method.
		if (
			getConfig( 'isSavedCardsEnabled' ) &&
			! getConfig( 'cartContainsSubscription' )
		) {
			gatewayConfig.supports.showSaveOption =
				paymentMethodsConfig[ event.value.type ].isReusable;
		}

		setIsUPEComplete( event.complete );
		setSelectedUPEPaymentType( event.value.type );
		setPaymentCountry( event.value.country );
	};

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
		shouldSavePayment || getConfig( 'cartContainsSubscription' )
			? 'always'
			: 'never';
	elementOptions.terms = getTerms( paymentMethodsConfig, showTerms );

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

	useEffect( () => {
		async function generateUPEAppearance() {
			// Generate UPE input styles.
			const upeAppearance = getAppearance( true );
			await api.saveUPEAppearance( upeAppearance, true );

			// Update appearance state
			setAppearance( upeAppearance );
		}
		if ( ! appearance ) {
			generateUPEAppearance();
		}

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
	}, [ paymentIntentId, hasRequestedIntent, api, errorMessage, appearance ] );

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

	const options = {
		clientSecret,
		appearance,
		fonts: fontRules,
	};

	return (
		<Elements stripe={ stripe } options={ options }>
			<WCPayUPEFields
				api={ api }
				paymentIntentId={ paymentIntentId }
				errorMessage={ errorMessage }
				{ ...props }
			/>
		</Elements>
	);
};

export default ConsumableWCPayFields;
