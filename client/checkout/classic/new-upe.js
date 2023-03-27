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
import { PAYMENT_METHOD_NAME_CARD } from '../constants.js';

jQuery( function ( $ ) {
	enqueueFraudScripts( getUPEConfig( 'fraudServices' ) );

	const paymentMethods = getUPEConfig( 'paymentMethodsConfig' );
	const publishableKey = getUPEConfig( 'publishableKey' );
	const isUPEEnabled = getUPEConfig( 'isUPEEnabled' );
	const isUPESplitEnabled = getUPEConfig( 'isUPESplitEnabled' );
	const isStripeLinkEnabled =
		paymentMethods.link !== undefined && paymentMethods.card !== undefined;
	const gatewayUPEComponents = {};
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
			publishableKey,
			accountId: getUPEConfig( 'accountId' ),
			forceNetworkSavedCards: getUPEConfig( 'forceNetworkSavedCards' ),
			locale: getUPEConfig( 'locale' ),
			isUPEEnabled,
			isUPESplitEnabled,
			isStripeLinkEnabled,
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
					createPaymentElement( paymentMethodType, domElement );
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

	let upeElement;
	let elements;

	function createPaymentElement( paymentMethodType, domElement ) {
		const options = {
			mode: 'payment',
			currency: 'usd',
			// TODO: get the amount from the order
			amount: 1000,
			paymentMethodCreation: 'manual',
			paymentMethodTypes: [ paymentMethodType ],
		};
		elements = api.getStripe().elements( options );
		gatewayUPEComponents[ paymentMethodType ].elements = elements;
		upeElement = elements.create( 'payment' );
		upeElement.mount( domElement );
		gatewayUPEComponents[ paymentMethodType ].upeElement = upeElement;
		upeElement.on( 'change', ( event ) => {
			const selectedUPEPaymentType =
				'link' !== event.value.type ? event.value.type : 'card';
			gatewayUPEComponents[ selectedUPEPaymentType ].country =
				event.value.country;
			gatewayUPEComponents[ selectedUPEPaymentType ].isUPEComplete =
				event.complete;
		} );
	}

	const handleEverything = async ( $form, paymentMethodType ) => {
		blockUI( $form );
		elements = gatewayUPEComponents[ paymentMethodType ].elements;
		elements.submit().then( ( result ) => {
			if ( result.error ) {
				console.log(
					'error occurred during Stripe UI element validation: ' +
						JSON.stringify( result.error )
				);
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
			console.log( 'error catched: ' + JSON.stringify( error ) );
		}
	};

	// Handle the checkout form when WooCommerce Payments is chosen.
	const wcpayPaymentMethods = [ PAYMENT_METHOD_NAME_CARD ].filter( Boolean );
	const checkoutEvents = wcpayPaymentMethods
		.map( ( method ) => `checkout_place_order_${ method }` )
		.join( ' ' );
	$( 'form.checkout' ).on( checkoutEvents, function () {
		const paymentMethodType = getSelectedPaymentMethod();

		handleEverything( $( this ), paymentMethodType );
		return false;
	} );

	function getSelectedPaymentMethod() {
		const paymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );
		const gatewayCardId = getUPEConfig( 'gatewayId' );
		let selectedGatewayId = null;

		// Handle payment method selection on the Checkout page or Add Payment Method page where class names differ.

		if ( null !== document.querySelector( 'li.wc_payment_method' ) ) {
			selectedGatewayId = document
				.querySelector(
					'li.wc_payment_method input.input-radio:checked'
				)
				.getAttribute( 'id' );
		} else if (
			null !== document.querySelector( 'li.woocommerce-PaymentMethod' )
		) {
			selectedGatewayId = document
				.querySelector(
					'li.woocommerce-PaymentMethod input.input-radio:checked'
				)
				.getAttribute( 'id' );
		}

		if ( 'payment_method_woocommerce_payments' === selectedGatewayId ) {
			selectedGatewayId = 'payment_method_woocommerce_payments_card';
		}

		let selectedPaymentMethod = null;

		for ( const paymentMethodType in paymentMethodsConfig ) {
			if (
				`payment_method_${ gatewayCardId }_${ paymentMethodType }` ===
				selectedGatewayId
			) {
				selectedPaymentMethod = paymentMethodType;
				break;
			}
		}

		return selectedPaymentMethod;
	}
} );
