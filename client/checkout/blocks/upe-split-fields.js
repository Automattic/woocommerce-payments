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
import { getUPEConfig } from 'utils/checkout';
import {
	getPaymentIntentFromSession,
	getCookieValue,
	getStripeElementOptions,
	getBlocksEmailValue,
	blocksShowLinkButtonHandler,
	useCustomerData,
	isLinkEnabled,
} from '../utils/upe';
import { decryptClientSecret } from '../utils/encryption';
import enableStripeLinkPaymentMethod from 'wcpay/checkout/stripe-link';
import { getAppearance, getFontRulesFromPage } from '../upe-styles';
import { useFingerprint } from './hooks';
import { LoadableBlock } from '../../components/loadable';
import {
	BLOCKS_SHIPPING_ADDRESS_FIELDS,
	BLOCKS_BILLING_ADDRESS_FIELDS,
} from '../constants';

const WCPayUPEFields = ( {
	api,
	activePaymentMethod,
	testingInstructions,
	billing: { billingData },
	shippingData,
	eventRegistration: { onPaymentProcessing, onCheckoutSuccess },
	emitResponse,
	paymentMethodId,
	upeMethods,
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

	const paymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );
	const isTestMode = getUPEConfig( 'testMode' );
	const testingInstructionsIfAppropriate = isTestMode
		? testingInstructions
		: '';
	const gatewayConfig = getPaymentMethods()[ upeMethods[ paymentMethodId ] ];
	const customerData = useCustomerData();

	useEffect( () => {
		if ( isLinkEnabled( paymentMethodsConfig ) ) {
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

					if ( key === 'line1' ) {
						customerAddress.address_1 = address.address[ key ];
					} else if ( key === 'line2' ) {
						customerAddress.address_2 = address.address[ key ];
					} else if ( key === 'postal_code' ) {
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
						document.getElementById( 'shipping-address_1' ) !== null
					);
				},
				shipping_fields: BLOCKS_SHIPPING_ADDRESS_FIELDS,
				billing_fields: BLOCKS_BILLING_ADDRESS_FIELDS,
				complete_billing: () => {
					return (
						document.getElementById( 'billing-address_1' ) !== null
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
				if ( upeMethods[ paymentMethodId ] !== activePaymentMethod ) {
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
							paymentMethod: paymentMethodId,
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
			onCheckoutSuccess(
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
							shippingData,
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
		const paymentType =
			event.value.type !== 'link' ? event.value.type : 'card';
		gatewayConfig.supports.showSaveOption =
			paymentMethodsConfig[ paymentType ].showSaveOption;

		setIsUPEComplete( event.complete );
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
		getUPEConfig( 'wcBlocksUPEAppearance' )
	);
	const [ fontRules ] = useState( getFontRulesFromPage() );
	const [ fingerprint, fingerprintErrorMessage ] = useFingerprint();
	const paymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );

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
		async function createIntent( paymentMethodType ) {
			try {
				const response = await api.createIntent( {
					fingerprint,
					paymentMethodType,
				} );
				const cartHash = getCookieValue( 'woocommerce_cart_hash' );
				if ( cartHash ) {
					paymentMethodsConfig[
						paymentMethodType
					].upePaymentIntentData =
						cartHash +
						'-' +
						response.id +
						'-' +
						response.client_secret;
				}
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

		function getOrCreateIntent( paymentMethodId ) {
			const {
				intentId,
				clientSecret: paymentClientSecret,
			} = getPaymentIntentFromSession(
				paymentMethodsConfig,
				paymentMethodId
			);
			if ( ! intentId ) {
				createIntent( paymentMethodId );
			} else {
				setPaymentIntentId( intentId );
				setClientSecret( paymentClientSecret );
			}
		}

		setHasRequestedIntent( true );
		getOrCreateIntent( props.paymentMethodId );
	}, [
		props.paymentMethodId,
		paymentIntentId,
		paymentMethodsConfig,
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
		<LoadableBlock
			isLoading={ ! appearance || ! clientSecret }
			numLines={ 3 }
		>
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
		</LoadableBlock>
	);
};

export const getSplitUPEFields = (
	upeName,
	upeMethods,
	api,
	testingInstructions
) => {
	return (
		<ConsumableWCPayFields
			paymentMethodId={ upeName }
			upeMethods={ upeMethods }
			api={ api }
			testingInstructions={ testingInstructions }
		/>
	);
};
