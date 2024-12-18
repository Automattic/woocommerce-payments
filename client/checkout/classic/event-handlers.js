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
	hasPaymentMethodCountryRestrictions,
	isUsingSavedPaymentMethod,
	togglePaymentMethodForCountry,
	isBillingInformationMissing,
} from '../utils/upe';
import {
	processPayment,
	mountStripePaymentElement,
	mountStripePaymentMethodMessagingElement,
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
import { recordUserEvent } from 'tracks';
import '../utils/copy-test-number';
import { SHORTCODE_BILLING_ADDRESS_FIELDS } from '../constants';

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
		maybeMountStripePaymentElement( 'shortcode_checkout' );
		injectStripePMMEContainers();
	} );

	$checkoutForm.on( generateCheckoutEventNames(), function () {
		if ( isBillingInformationMissing( this ) ) {
			return;
		}

		return processPaymentIfNotUsingSavedMethod( $( this ) );
	} );

	$checkoutForm.on( 'click', '#place_order', function () {
		// Use the existing utility function to check if any WCPay payment method is selected
		const selectedPaymentMethod = getSelectedUPEGatewayPaymentMethod();

		if ( ! selectedPaymentMethod ) {
			return;
		}

		recordUserEvent( 'checkout_place_order_button_click' );
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

	if ( $addPaymentMethodForm.length ) {
		maybeMountStripePaymentElement( 'add_payment_method' );
	}

	if ( $payForOrderForm.length ) {
		maybeMountStripePaymentElement( 'shortcode_checkout' );
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
		if ( getSelectedUPEGatewayPaymentMethod() === null ) {
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

	async function injectStripePMMEContainers() {
		const bnplMethods = [ 'affirm', 'afterpay_clearpay', 'klarna' ];
		const labelBase = 'payment_method_woocommerce_payments_';
		const paymentMethods = getUPEConfig( 'paymentMethodsConfig' );
		const paymentMethodsKeys = Object.keys( paymentMethods );
		const cartData = await api.pmmeGetCartData();

		for ( const method of paymentMethodsKeys ) {
			if ( bnplMethods.includes( method ) ) {
				const targetLabel = document.querySelector(
					`label[for="${ labelBase }${ method }"]`
				);
				const containerID = `stripe-pmme-container-${ method }`;

				if ( document.getElementById( containerID ) ) {
					document.getElementById( containerID ).innerHTML = '';
				}

				if ( targetLabel ) {
					// wrapInner target label in a span.woopayments-inner-label if it's not already
					let targetLabelInnerSpan = targetLabel.querySelector(
						'span.woopayments-inner-label'
					);
					if ( ! targetLabelInnerSpan ) {
						const targetLabelInner = targetLabel.innerHTML;
						targetLabel.innerHTML = '';
						targetLabelInnerSpan = document.createElement( 'span' );
						targetLabelInnerSpan.classList.add(
							'woopayments-inner-label'
						);
						targetLabelInnerSpan.innerHTML = targetLabelInner;
						targetLabel.appendChild( targetLabelInnerSpan );
					}

					let spacer = targetLabel.querySelector( 'span.spacer' );
					if ( ! spacer ) {
						spacer = document.createElement( 'span' );
						spacer.classList.add( 'spacer' );
						spacer.innerHTML = '&nbsp;';
						targetLabel.insertBefore(
							spacer,
							targetLabelInnerSpan
						);
					}

					let container = document.getElementById( containerID );
					if ( ! container ) {
						container = document.createElement( 'span' );
						container.id = containerID;
						container.dataset.paymentMethodType = method;
						container.classList.add( 'stripe-pmme-container' );
						targetLabelInnerSpan.appendChild( container );
					}

					const currentCountry =
						cartData?.billing_address?.country ||
						getUPEConfig( 'storeCountry' );

					if (
						paymentMethods[ method ]?.countries.length === 0 ||
						paymentMethods[ method ]?.countries?.includes(
							currentCountry
						)
					) {
						await mountStripePaymentMethodMessagingElement(
							api,
							container,
							{
								amount: cartData?.totals?.total_price,
								currency: cartData?.totals?.currency_code,
								decimalPlaces:
									cartData?.totals?.currency_minor_unit,
								country: currentCountry,
							},
							'shortcode_checkout'
						);
					}
				}
			}
		}
	}

	function processPaymentIfNotUsingSavedMethod( $form ) {
		const paymentMethodType = getSelectedUPEGatewayPaymentMethod();
		if ( ! isUsingSavedPaymentMethod( paymentMethodType ) ) {
			return processPayment( api, $form, paymentMethodType );
		}
	}

	async function maybeMountStripePaymentElement( elementsLocation ) {
		const $upeForms = $( '.wcpay-upe-form' );
		const $upeElements = $upeForms.find( '.wcpay-upe-element' );

		if ( $upeElements.length && ! $upeElements.children().length ) {
			for ( const upeElement of $upeElements.toArray() ) {
				await mountStripePaymentElement(
					api,
					upeElement,
					elementsLocation
				);
				restrictPaymentMethodToLocation( upeElement );
			}
			maybeEnableStripeLink( api );
		}
	}

	function restrictPaymentMethodToLocation( upeElement ) {
		if ( hasPaymentMethodCountryRestrictions( upeElement ) ) {
			togglePaymentMethodForCountry( upeElement );

			const billingInput = upeElement
				?.closest( 'form.checkout' )
				?.querySelector(
					`[name="${ SHORTCODE_BILLING_ADDRESS_FIELDS.country }"]`
				);
			if ( billingInput ) {
				// this event only applies to the checkout form, but not "place order" or "add payment method" pages.
				$( billingInput ).on( 'change', function () {
					togglePaymentMethodForCountry( upeElement );
				} );
			}
		}
	}
} );
