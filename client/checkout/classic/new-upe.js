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
import { getSelectedUPEGatewayPaymentMethod } from '../utils/upe';
import showErrorCheckout from '../utils/show-error-checkout';

jQuery( function ( $ ) {
	enqueueFraudScripts( getUPEConfig( 'fraudServices' ) );

	const gatewayUPEComponents = {};
	const paymentMethods = getUPEConfig( 'paymentMethodsConfig' );
	for ( const paymentMethodType in paymentMethods ) {
		gatewayUPEComponents[ paymentMethodType ] = {
			elements: null,
			upeElement: null,
			isUPEComplete: null,
			country: null,
		};
	}

	const api = new WCPayAPI(
		{
			publishableKey: getUPEConfig( 'publishableKey' ),
			accountId: getUPEConfig( 'accountId' ),
			forceNetworkSavedCards: getUPEConfig( 'forceNetworkSavedCards' ),
			locale: getUPEConfig( 'locale' ),
		},
		apiRequest
	);

	$( document.body ).on( 'updated_checkout', () => {
		$( '.wcpay-upe-element' )
			.toArray()
			.forEach( ( domElement ) => {
				const paymentMethodType = $( domElement ).data(
					'payment-method-type'
				);
				const stripeElement =
					gatewayUPEComponents[ paymentMethodType ].upeElement;
				if ( stripeElement ) {
					stripeElement.mount( domElement );
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

	async function createAndMountPaymentElement(
		paymentMethodType,
		domElement
	) {
		const options = {
			mode: 'payment',
			currency: getUPEConfig( 'currency' ).toLowerCase(),
			amount: Number( getUPEConfig( 'cartTotal' ) ),
			paymentMethodCreation: 'manual',
			paymentMethodTypes: [ paymentMethodType ],
		};
		const elements = api.getStripe().elements( options );
		gatewayUPEComponents[ paymentMethodType ].elements = elements;

		const upeElement = elements.create( 'payment' );
		upeElement.mount( domElement );
		gatewayUPEComponents[ paymentMethodType ].upeElement = upeElement;

		upeElement.on( 'change', ( event ) => {
			const selectedUPEPaymentType = event.value.type;
			gatewayUPEComponents[ selectedUPEPaymentType ].country =
				event.value.country;
			gatewayUPEComponents[ selectedUPEPaymentType ].isUPEComplete =
				event.complete;
		} );
	}

	const handleCheckout = async ( $form, paymentMethodType ) => {
		blockUI( $form );
		const elements = gatewayUPEComponents[ paymentMethodType ].elements;
		elements.submit().then( ( result ) => {
			if ( result.error ) {
				showErrorCheckout( result.error.message );
			}
		} );
		const pm = await api.getStripe().createPaymentMethod( {
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
		} );

		try {
			const fields = {
				...$form.serializeArray().reduce( ( obj, field ) => {
					obj[ field.name ] = field.value;
					return obj;
				}, {} ),
				wc_payment_method: pm.paymentMethod.id,
			};

			const response = await api.processCheckout( fields );
			window.location.href = response.redirect;
		} catch ( error ) {
			showErrorCheckout( error.message );
		}
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

		handleCheckout( $( this ), paymentMethodType );
		return false;
	} );
} );
