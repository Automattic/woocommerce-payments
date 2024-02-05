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
import { recordUserEvent, events } from 'tracks';

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
	showAuthenticationModalIfRequired( api )
		.then( ( setupIntent ) => {
			if ( typeof setupIntent !== 'object' ) return;

			const input = document.createElement( 'input' );
			input.type = 'hidden';
			input.id = 'wcpay-setup-intent';
			input.name = 'wcpay-setup-intent';
			input.value = setupIntent.id;

			$addPaymentMethodForm.append( input );
			// submit again to show success message and add confirmed intent to the WC customer
			$addPaymentMethodForm.submit();
		} )
		.finally( () => {
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

		recordUserEvent( events.PLACE_ORDER_CLICK );
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

		if ( window.location.href.indexOf( 'wcpay-confirm-intent' ) > -1 ) {
			return;
		}

		// WC core calls block() when add_payment_method form is submitted, so we need to enable the ignore flag here to avoid
		// the overlay blink when the form is blocked twice.
		$.blockUI.defaults.ignoreIfBlocked = true;

		return processPayment(
			api,
			$addPaymentMethodForm,
			getSelectedUPEGatewayPaymentMethod()
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
		const $wcpayUpeElement = $( '.wcpay-upe-element' );
		if ( $wcpayUpeElement.length && ! $wcpayUpeElement.children().length ) {
			for ( const upeElement of $wcpayUpeElement.toArray() ) {
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
