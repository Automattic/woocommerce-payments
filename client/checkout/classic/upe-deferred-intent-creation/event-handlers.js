/* global jQuery */

/**
 * Internal dependencies
 */
import { getUPEConfig } from 'wcpay/utils/checkout';
import {
	generateCheckoutEventNames,
	getSelectedUPEGatewayPaymentMethod,
	isLinkEnabled,
	isUsingSavedPaymentMethod,
} from '../../utils/upe';
import {
	processPayment,
	mountStripePaymentElement,
	renderTerms,
	createAndConfirmSetupIntent,
	maybeEnableStripeLink,
} from './payment-processing';
import enqueueFraudScripts from 'fraud-scripts';
import { showAuthenticationModalIfRequired } from './3ds-flow-handling';
import WCPayAPI from 'wcpay/checkout/api';
import apiRequest from '../../utils/request';
import { handleWooPayEmailInput } from 'wcpay/checkout/woopay/email-input-iframe';
import { isPreviewing } from 'wcpay/checkout/preview';

jQuery( function ( $ ) {
	enqueueFraudScripts( getUPEConfig( 'fraudServices' ) );
	const api = new WCPayAPI(
		{
			publishableKey: getUPEConfig( 'publishableKey' ),
			accountId: getUPEConfig( 'accountId' ),
			forceNetworkSavedCards: getUPEConfig( 'forceNetworkSavedCards' ),
			locale: getUPEConfig( 'locale' ),
			isUPEEnabled: getUPEConfig( 'isUPEEnabled' ),
			isStripeLinkEnabled: isLinkEnabled(
				getUPEConfig( 'paymentMethodsConfig' )
			),
			isUPEDeferredEnabled: getUPEConfig( 'isUPEDeferredEnabled' ),
		},
		apiRequest
	);
	showAuthenticationModalIfRequired( api );

	$( document.body ).on( 'updated_checkout', () => {
		maybeMountStripePaymentElement();
	} );

	$( 'form.checkout' ).on( generateCheckoutEventNames(), function () {
		return processPaymentIfNotUsingSavedMethod( $( this ) );
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
		return processPaymentIfNotUsingSavedMethod( $( 'form#order_review' ) );
	} );

	if ( getUPEConfig( 'isWooPayEnabled' ) && ! isPreviewing() ) {
		handleWooPayEmailInput( '#billing_email', api );
	}

	function processPaymentIfNotUsingSavedMethod( $form ) {
		const paymentMethodType = getSelectedUPEGatewayPaymentMethod();
		if ( ! isUsingSavedPaymentMethod( paymentMethodType ) ) {
			return processPayment( api, $form, paymentMethodType );
		}
	}

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
			maybeEnableStripeLink( api );
		}
	}
} );
