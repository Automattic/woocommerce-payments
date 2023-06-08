/* global jQuery */

/**
 * Internal dependencies
 */
import { getUPEConfig } from 'wcpay/utils/checkout';
import {
	generateCheckoutEventNames,
	getSelectedUPEGatewayPaymentMethod,
	isUsingSavedPaymentMethod,
} from '../../utils/upe';
import {
	processPayment,
	mountStripePaymentElement,
	renderTerms,
	createAndConfirmSetupIntent,
	handleOrderPayment,
} from './payment-processing';
import enqueueFraudScripts from 'fraud-scripts';
import { showAuthenticationModalIfRequired } from './3ds-flow-handling';
import WCPayAPI from 'wcpay/checkout/api';
import apiRequest from '../../utils/request';

jQuery( function ( $ ) {
	enqueueFraudScripts( getUPEConfig( 'fraudServices' ) );
	const api = new WCPayAPI(
		{
			publishableKey: getUPEConfig( 'publishableKey' ),
			accountId: getUPEConfig( 'accountId' ),
			forceNetworkSavedCards: getUPEConfig( 'forceNetworkSavedCards' ),
			locale: getUPEConfig( 'locale' ),
		},
		apiRequest
	);
	showAuthenticationModalIfRequired( api );

	$( document.body ).on( 'updated_checkout', () => {
		maybeMountStripePaymentElement();
	} );

	$( 'form.checkout' ).on( generateCheckoutEventNames(), function () {
		const paymentMethodType = getSelectedUPEGatewayPaymentMethod();
		if ( ! isUsingSavedPaymentMethod( paymentMethodType ) ) {
			return processPayment( api, jQuery( this ), paymentMethodType );
		}
	} );

	window.addEventListener( 'hashchange', () => {
		if ( window.location.hash.startsWith( '#wcpay-confirm-' ) ) {
			showAuthenticationModalIfRequired( api );
		}
	} );

	document.addEventListener( 'change', function ( event ) {
		if (
			event.target &&
			'wc-woocommerce_payments-new-payment-method' === event.target.id
		) {
			renderTerms( event );
		}
	} );

	if (
		$( 'form#add_payment_method' ).length ||
		$( 'form#order_review' ).length
	) {
		if ( getUPEConfig( 'isUPEEnabled' ) ) {
			maybeMountStripePaymentElement();
		}
	}

	$( 'form#add_payment_method' ).on( 'submit', function () {
		// WC core calls block() when add_payment_method form is submitted, so we need to enable the ignore flag here to avoid
		// the overlay blink when the form is blocked twice.
		$.blockUI.defaults.ignoreIfBlocked = true;

		return processPayment(
			api,
			$( 'form#add_payment_method' ),
			getSelectedUPEGatewayPaymentMethod(),
			createAndConfirmSetupIntent
		);
	} );

	$( 'form#order_review' ).on( 'submit', function () {
		const paymentMethodType = getSelectedUPEGatewayPaymentMethod();
		if ( ! isUsingSavedPaymentMethod( paymentMethodType ) ) {
			return processPayment(
				api,
				$( 'form#order_review' ),
				paymentMethodType,
				handleOrderPayment
			);
		}
	} );

	function maybeMountStripePaymentElement() {
		if (
			$( '.wcpay-upe-element' ).length &&
			! $( '.wcpay-upe-element' ).children().length
		) {
			$( '.wcpay-upe-element' )
				.toArray()
				.forEach( ( domElement ) =>
					mountStripePaymentElement( api, domElement )
				);
		}
	}
} );
