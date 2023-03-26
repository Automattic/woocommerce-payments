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

	const submitForm = async ( $form, { id } ) => {
		const formFields = $form.serializeArray().reduce( ( obj, field ) => {
			obj[ field.name ] = field.value;
			return obj;
		}, {} );
		const additionalOptions = {
			wc_payment_method: id,
		};
		await api
			.processCheckout( {
				...formFields,
				...additionalOptions,
			} )
			.then( ( response ) => {
				console.log( 'got response: ' + JSON.stringify( response ) );
				window.location.href = response.redirect;
			} );
		// const paymentSelector = '#wcpay-upe-element';

		// // // Populate form with the payment method.
		// $( paymentSelector ).val( id );

		// // // Re-submit the form.
		// $form.submit();

		console.log( 'submitting form and the pm is: ' + JSON.stringify( id ) );
	};

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

	let upeElement;
	let elements;

	function createPaymentElement( paymentMethodType, domElement ) {
		const options = {
			mode: 'payment',
			currency: 'usd',
			amount: 1000,
			paymentMethodCreation: 'manual',
		};
		elements = api.getStripe().elements( options );
		upeElement = elements.create( 'payment' );
		upeElement.mount( domElement );
	}

	// Handle the checkout form when WooCommerce Payments is chosen.
	const wcpayPaymentMethods = [ PAYMENT_METHOD_NAME_CARD ].filter( Boolean );
	const checkoutEvents = wcpayPaymentMethods
		.map( ( method ) => `checkout_place_order_${ method }` )
		.join( ' ' );
	$( 'form.checkout' ).on( checkoutEvents, async function () {
		await elements.submit();
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
			.then( ( { paymentMethod } ) => {
				submitForm( $( this ), paymentMethod );
			} )
			.catch( ( error ) => {
				console.log( 'error occurred: ' + JSON.stringify( error ) );
			} );
	} );
} );
