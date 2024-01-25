/* global jQuery */

/**
 * Internal dependencies
 */
import './style.scss';
import { getUPEConfig } from 'wcpay/utils/checkout';
import {
	generateCheckoutEventNames,
	getSelectedUPEGatewayPaymentMethod,
	isLinkEnabled,
	isPaymentMethodRestrictedToLocation,
	isUsingSavedPaymentMethod,
	togglePaymentMethodForCountry,
} from '../utils/upe';
import {
	processPayment,
	mountStripePaymentElement,
	renderTerms,
	createAndConfirmSetupIntent,
	maybeEnableStripeLink,
	blockUI,
	unblockUI,
} from './payment-processing';
import enqueueFraudScripts from 'fraud-scripts';
import { showAuthenticationModalIfRequired } from './3ds-flow-handling';
import WCPayAPI from 'wcpay/checkout/api';
import apiRequest from '../utils/request';
import { handleWooPayEmailInput } from 'wcpay/checkout/woopay/email-input-iframe';
import { isPreviewing } from 'wcpay/checkout/preview';
import wcpayTracks from 'tracks';

jQuery( function ( $ ) {
	enqueueFraudScripts( getUPEConfig( 'fraudServices' ) );
	const publishableKey = getUPEConfig( 'publishableKey' );

	if ( ! publishableKey ) {
		// If no configuration is present, probably this is not the checkout page.
		return;
	}

	const $checkoutForm = $( 'form.checkout' );
	const $addPaymentMethodForm = $( 'form#add_payment_method' );
	const $payForOrderForm = $( 'form#order_review' );

	// creating a new jQuery object containing all the forms that need to be updated on submit, failure, or other events.
	const $forms = jQuery( $checkoutForm )
		.add( $addPaymentMethodForm )
		.add( $payForOrderForm );

	const api = new WCPayAPI(
		{
			publishableKey: publishableKey,
			accountId: getUPEConfig( 'accountId' ),
			forceNetworkSavedCards: getUPEConfig( 'forceNetworkSavedCards' ),
			locale: getUPEConfig( 'locale' ),
			isStripeLinkEnabled: isLinkEnabled(
				getUPEConfig( 'paymentMethodsConfig' )
			),
		},
		apiRequest
	);

	blockUI( $forms );
	showAuthenticationModalIfRequired( api ).finally( () => {
		unblockUI( $forms );
	} );

	$( document.body ).on( 'updated_checkout', () => {
		maybeMountStripePaymentElement();
	} );

	$checkoutForm.on( generateCheckoutEventNames(), function () {
		return processPaymentIfNotUsingSavedMethod( $( this ) );
	} );

	$checkoutForm.on( 'click', '#place_order', function () {
		const isWCPay = document.getElementById(
			'payment_method_woocommerce_payments'
		)?.checked;

		if ( ! isWCPay ) {
			return;
		}

		wcpayTracks.recordUserEvent( wcpayTracks.events.PLACE_ORDER_CLICK );
	} );

	window.addEventListener( 'hashchange', () => {
		if ( window.location.hash.startsWith( '#wcpay-confirm-' ) ) {
			blockUI( $forms );
			showAuthenticationModalIfRequired( api, $forms ).finally( () => {
				unblockUI( $forms );
			} );
		}
	} );

	document.addEventListener( 'change', function ( event ) {
		if (
			event.target &&
			event.target.id === 'wc-woocommerce_payments-new-payment-method'
		) {
			renderTerms( event );
		}
	} );

	if ( $addPaymentMethodForm.length || $payForOrderForm.length ) {
		maybeMountStripePaymentElement();
	}

	$addPaymentMethodForm.on( 'submit', function () {
		if (
			$addPaymentMethodForm
				.find( "input:checked[name='payment_method']" )
				.val() !== 'woocommerce_payments'
		) {
			return;
		}

		// WC core calls block() when add_payment_method form is submitted, so we need to enable the ignore flag here to avoid
		// the overlay blink when the form is blocked twice.
		$.blockUI.defaults.ignoreIfBlocked = true;

		return processPayment(
			api,
			$addPaymentMethodForm,
			getSelectedUPEGatewayPaymentMethod(),
			createAndConfirmSetupIntent
		);
	} );

	$payForOrderForm.on( 'submit', function () {
		if (
			$payForOrderForm
				.find( "input:checked[name='payment_method']" )
				.val() !== 'woocommerce_payments'
		) {
			return;
		}

		return processPaymentIfNotUsingSavedMethod( $payForOrderForm );
	} );

	if (
		getUPEConfig( 'isWooPayEnabled' ) &&
		getUPEConfig( 'isWooPayEmailInputEnabled' ) &&
		! isPreviewing()
	) {
		handleWooPayEmailInput( '#billing_email', api );
	}

	function processPaymentIfNotUsingSavedMethod( $form ) {
		const paymentMethodType = getSelectedUPEGatewayPaymentMethod();
		if ( ! isUsingSavedPaymentMethod( paymentMethodType ) ) {
			return processPayment( api, $form, paymentMethodType );
		}
	}

	async function maybeMountStripePaymentElement() {
		if (
			$( '.wcpay-upe-element' ).length &&
			! $( '.wcpay-upe-element' ).children().length
		) {
			for ( const upeElement of $( '.wcpay-upe-element' ).toArray() ) {
				await mountStripePaymentElement( api, upeElement );
				restrictPaymentMethodToLocation( upeElement );
			}
			maybeEnableStripeLink( api );
		}
	}

	function restrictPaymentMethodToLocation( upeElement ) {
		if ( isPaymentMethodRestrictedToLocation( upeElement ) ) {
			togglePaymentMethodForCountry( upeElement );

			// this event only applies to the checkout form, but not "place order" or "add payment method" pages.
			$( '#billing_country' ).on( 'change', function () {
				togglePaymentMethodForCountry( upeElement );
			} );
		}
	}
} );
