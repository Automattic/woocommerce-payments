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

	const submitForm = ( generatedPaymentMethod ) => {
		console.log(
			'submitting form and the pm is: ' + generatedPaymentMethod
		);
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

	function createPaymentElement( paymentMethodType, domElement ) {
		const options = {
			mode: 'payment',
			currency: 'usd',
			amount: 1000,
		};
		const elements = api.getStripe().elements( options );
		upeElement = elements.create( 'payment' );
		upeElement.mount( domElement );
	}

	// Handle the checkout form when WooCommerce Payments is chosen.
	const wcpayPaymentMethods = [ PAYMENT_METHOD_NAME_CARD ].filter( Boolean );
	const checkoutEvents = wcpayPaymentMethods
		.map( ( method ) => `checkout_place_order_${ method }` )
		.join( ' ' );
	$( 'form.checkout' ).on( checkoutEvents, function () {
		const request = api.generatePaymentMethodRequest( {
			type: 'payment',
			card: upeElement,
		} );
		// Populate payment method owner details.
		const billingName = $( '#billing_first_name' ).length
			? (
					$( '#billing_first_name' ).val() +
					' ' +
					$( '#billing_last_name' ).val()
			  ).trim()
			: undefined;

		request.setBillingDetail( 'name', billingName );
		request.setBillingDetail( 'email', $( '#billing_email' ).val() );
		request.setBillingDetail( 'phone', $( '#billing_phone' ).val() );
		request.setAddressDetail( 'city', $( '#billing_city' ).val() );
		request.setAddressDetail( 'country', $( '#billing_country' ).val() );
		request.setAddressDetail( 'line1', $( '#billing_address_1' ).val() );
		request.setAddressDetail( 'line2', $( '#billing_address_2' ).val() );
		request.setAddressDetail(
			'postal_code',
			$( '#billing_postcode' ).val()
		);
		request.setAddressDetail( 'state', $( '#billing_state' ).val() );

		request
			.send()
			.then( ( { paymentMethod } ) => {
				submitForm( paymentMethod );
				// $( '#wcpay-payment-method' ).val( paymentMethod.id );
				// $( 'form.checkout' ).submit();
			} )
			.catch( ( error ) => {
				console.log( 'error occurred: ' + error );
			} );
	} );
} );
