/* global jQuery */
// create payment methods
// submit order payment

/**
 * Internal dependencies
 */
import { getUPEConfig } from 'wcpay/utils/checkout';
import WCPayAPI from '../api';
import enqueueFraudScripts from 'fraud-scripts';
import apiRequest from '../utils/request';
import {
	PAYMENT_METHOD_NAME_BANCONTACT,
	PAYMENT_METHOD_NAME_BECS,
	PAYMENT_METHOD_NAME_CARD,
	PAYMENT_METHOD_NAME_EPS,
	PAYMENT_METHOD_NAME_GIROPAY,
	PAYMENT_METHOD_NAME_IDEAL,
	PAYMENT_METHOD_NAME_P24,
	PAYMENT_METHOD_NAME_SEPA,
	PAYMENT_METHOD_NAME_SOFORT,
} from '../constants.js';
import {
	getSelectedUPEGatewayPaymentMethod,
	getUpeSettings,
} from '../utils/upe';
import showErrorCheckout from '../utils/show-error-checkout';
import { getAppearance } from '../upe-styles';
import {
	appendFingerprintInputToForm,
	getFingerprint,
} from '../utils/fingerprint';

jQuery( function ( $ ) {
	const paymentMethods = getUPEConfig( 'paymentMethodsConfig' );
	const gatewayUPEComponents = {};
	const api = new WCPayAPI(
		{
			publishableKey: getUPEConfig( 'publishableKey' ),
			accountId: getUPEConfig( 'accountId' ),
			forceNetworkSavedCards: getUPEConfig( 'forceNetworkSavedCards' ),
			locale: getUPEConfig( 'locale' ),
		},
		apiRequest
	);

	let fingerprint = null;
	let appearance = getUPEConfig( 'upeAppearance' );

	enqueueFraudScripts( getUPEConfig( 'fraudServices' ) );
	initAppearance();
	initStripeElements();

	function initAppearance() {
		if ( ! appearance ) {
			appearance = getAppearance();
			api.saveUPEAppearance( appearance );
		}
	}

	function initStripeElements() {
		for ( const paymentMethodType in paymentMethods ) {
			gatewayUPEComponents[ paymentMethodType ] = {
				elements: null,
				upeElement: null,
			};
		}
	}

	/**
	 * This is the entry-point of this file, which is responsible for mounting the UPE elements.
	 * It is called when the page is loaded.
	 *
	 */
	$( document.body ).on( 'updated_checkout', () => {
		$( '.wcpay-upe-element' )
			.toArray()
			.forEach( ( domElement ) => {
				const paymentMethodType = $( domElement ).data(
					'payment-method-type'
				);
				const upeElement =
					gatewayUPEComponents[ paymentMethodType ].upeElement;
				if ( upeElement ) {
					upeElement.mount( domElement );
				} else {
					createAndMountPaymentElement(
						paymentMethodType,
						domElement
					);
				}
			} );
	} );

	/**
	 * Block UI to indicate processing and avoid duplicate submission.
	 *
	 * @param {Object} $form The jQuery object for the form.
	 */
	const blockUI = ( $form ) => {
		$form.addClass( 'processing' ).block( {
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6,
			},
		} );
	};

	/**
	 * Creates the UPE element and mounts it to the DOM element.
	 * No Stripe PaymentIntent is created at this point.
	 *
	 * @param {string} paymentMethodType The type of payment method, e.g. 'card', 'giropay', etc.
	 * @param {string} domElement The selector of the DOM element of particular payment method to mount the UPE element to.
	 */
	async function createAndMountPaymentElement(
		paymentMethodType,
		domElement
	) {
		const amount = Number( getUPEConfig( 'cartTotal' ) );
		const options = {
			mode: 1 > amount ? 'setup' : 'payment',
			currency: getUPEConfig( 'currency' ).toLowerCase(),
			amount: Number( getUPEConfig( 'cartTotal' ) ),
			paymentMethodCreation: 'manual',
			paymentMethodTypes: [ paymentMethodType ],
			appearance: appearance,
		};

		if ( ! fingerprint ) {
			try {
				const { visitorId } = await getFingerprint();
				fingerprint = visitorId;
			} catch ( error ) {
				// Do not mount element if fingerprinting is not available
				showErrorCheckout( error.message );

				return;
			}
		}

		const elements = api.getStripe().elements( options );
		gatewayUPEComponents[ paymentMethodType ].elements = elements;

		const upeElement = elements.create( 'payment', {
			...getUpeSettings(),
			wallets: {
				applePay: 'never',
				googlePay: 'never',
			},
		} );

		upeElement.mount( domElement );
		gatewayUPEComponents[ paymentMethodType ].upeElement = upeElement;
	}

	let paymentMethodGenerated;

	const handleCheckout = ( $form, paymentMethodType ) => {
		if ( paymentMethodGenerated ) {
			paymentMethodGenerated = null;
			return;
		}

		blockUI( $form );
		const elements = gatewayUPEComponents[ paymentMethodType ].elements;
		elements.submit().then( ( result ) => {
			if ( result.error ) {
				showErrorCheckout( result.error.message );
			}
		} );
		api.getStripe()
			.createPaymentMethod( {
				elements,
				params: {
					billing_details: {
						name: $( '#billing_first_name' ).length
							? (
									$( '#billing_first_name' ).val() +
									' ' +
									$( '#billing_last_name' ).val()
							  ).trim()
							: undefined,
						email: $( '#billing_email' ).val(),
						phone: $( '#billing_phone' ).val(),
						address: {
							city: $( '#billing_city' ).val(),
							country: $( '#billing_country' ).val(),
							line1: $( '#billing_address_1' ).val(),
							line2: $( '#billing_address_2' ).val(),
							postal_code: $( '#billing_postcode' ).val(),
							state: $( '#billing_state' ).val(),
						},
					},
				},
			} )
			.then( function ( paymentMethodObject ) {
				paymentMethodGenerated = true;

				appendFingerprintInputToForm( $form, fingerprint );
				appendPaymentMethodIdToForm(
					$form,
					paymentMethodObject.paymentMethod.id
				);
				$form.removeClass( 'processing' ).submit();
			} )
			.catch( ( error ) => {
				$form.removeClass( 'processing' ).unblock();
				showErrorCheckout( error.message );
			} );
		// Prevent form submission so that we can fire it once a payment method has been generated.
		return false;
	};

	// Handle the checkout form when WooCommerce Payments is chosen.
	const wcpayPaymentMethods = [
		PAYMENT_METHOD_NAME_BANCONTACT,
		PAYMENT_METHOD_NAME_BECS,
		PAYMENT_METHOD_NAME_EPS,
		PAYMENT_METHOD_NAME_GIROPAY,
		PAYMENT_METHOD_NAME_IDEAL,
		PAYMENT_METHOD_NAME_P24,
		PAYMENT_METHOD_NAME_SEPA,
		PAYMENT_METHOD_NAME_SOFORT,
		PAYMENT_METHOD_NAME_CARD,
	];
	const checkoutEvents = wcpayPaymentMethods
		.map( ( method ) => `checkout_place_order_${ method }` )
		.join( ' ' );
	$( 'form.checkout' ).on( checkoutEvents, function () {
		const paymentMethodType = getSelectedUPEGatewayPaymentMethod();
		return handleCheckout( $( this ), paymentMethodType );
	} );

	function appendPaymentMethodIdToForm( $form, paymentMethodId ) {
		// Remove any existing payment method inputs.
		$form.find( 'input[name="wc_payment_method"]' ).remove();

		$form.append(
			`<input type="hidden" name="wc_payment_method" value="${ paymentMethodId }" />`
		);
	}
} );
