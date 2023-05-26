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
	checkout,
	mountStripePaymentElement,
	renderTerms,
	createAndConfirmSetupIntent,
} from './stripe-checkout';
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
	} );

	$( 'form.checkout' ).on( generateCheckoutEventNames(), function () {
		const paymentMethodType = getSelectedUPEGatewayPaymentMethod();
		if ( ! isUsingSavedPaymentMethod( paymentMethodType ) ) {
			return checkout( api, jQuery( this ), paymentMethodType );
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

	if ( $( 'form#add_payment_method' ).length ) {
		if (
			$( '.wcpay-upe-element' ).length &&
			! $( '.wcpay-upe-element' ).children().length &&
			getUPEConfig( 'isUPEEnabled' )
		) {
			$( '.wcpay-upe-element' )
				.toArray()
				.forEach( ( domElement ) =>
					mountStripePaymentElement( api, domElement )
				);
		}
	}

	$( 'form#add_payment_method' ).on( 'submit', function () {
		$.blockUI.defaults.ignoreIfBlocked = true;

		return checkout(
			api,
			$( 'form#add_payment_method' ),
			getSelectedUPEGatewayPaymentMethod(),
			createAndConfirmSetupIntent
		);
	} );
} );
