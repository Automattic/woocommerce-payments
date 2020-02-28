/* eslint-disable strict, no-var */
/* global jQuery, Stripe, wcpay_config */
jQuery( function( $ ) {
	'use strict';

	/* eslint-disable-next-line camelcase */
	var stripe = new Stripe( wcpay_config.publishableKey, {
		/* eslint-disable-next-line camelcase */
		stripeAccount: wcpay_config.accountId,
	} );
	var elements = stripe.elements();

	// In the future this object will be loaded with customer information through `wp_localize_script`.
	var preparedCustomerData = {};

	// Create a card element.
	var cardElement = elements.create( 'card', {
		hidePostalCode: true,
		classes: { base: 'wcpay-card-mounted' },
	} );

	// Only attempt to mount the card element once that section of the page has loaded. We can use the updated_checkout
	// event for this. This part of the page can also reload based on changes to checkout details, so we call unmount
	// first to ensure the card element is re-mounted correctly.
	$( document.body ).on( 'updated_checkout', function() {
		// Don't re-mount if already mounted in DOM.
		if ( $( '#wcpay-card-element' ).children().length ) {
			return;
		}

		cardElement.unmount();
		cardElement.mount( '#wcpay-card-element' );
	} );

	if ( $( 'form#add_payment_method' ).length || $( 'form#order_review' ).length ) {
		cardElement.mount( '#wcpay-card-element' );
	}

	// Update the validation state based on the element's state.
	cardElement.addEventListener( 'change', function( event ) {
		var displayError = jQuery( '#wcpay-errors' );
		if ( event.error ) {
			displayError.html( '<ul class="woocommerce-error"><li /></ul>' )
				.find( 'li' ).text( event.error.message );
		} else {
			displayError.empty();
		}
	} );

	/**
	 * Adds a customer value to an object if the value exists and is non-empty.
	 *
	 * @param {object} customerObj The object that the value should be loaded to.
	 * @param {string} prop        The name of the prop in the object.
	 * @param {string} inputId     The ID of the input on the page (or the data, preloaded by the server.)
	 */
	var setCustomerValue = function( customerObj, prop, inputId ) {
		var value;

		// Try to load the value from the fields on the page.
		if ( 'name' === inputId ) {
			// If and whenever the first/last name fields do not exist on the page, this will be an empty string.
			value = ( $( '#billing_first_name' ).val() + ' ' + $( '#billing_last_name' ).val() ).trim();
		} else {
			// No need to check whether the element exists, `$.fn.val()` would return `undefined`.
			value = $( '#' + inputId ).val();
		}

		// Fall back to the value in `preparedCustomerData`.
		if ( ( 'undefined' === typeof value ) || 0 === value.length ) {
			value = preparedCustomerData[ inputId ]; // `undefined` if not set.
		}

		if ( ( 'undefined' !== typeof value ) && 0 < value.length ) {
			customerObj[ prop ] = value;
		}
	};

	/**
	 * Loads all necessary billing details for payment methods.
	 *
	 * @return {object} An object, containing email, name, phone & an address.
	 */
	var loadBillingDetails = function() {
		var billingDetails = {},
			billingAddress = {};

		// Populate billing details.
		setCustomerValue( billingDetails, 'name', 'name' );
		setCustomerValue( billingDetails, 'email', 'billing_email' );
		setCustomerValue( billingDetails, 'phone', 'billing_phone' );

		// Populate the billing address.
		setCustomerValue( billingAddress, 'city', 'billing_city' );
		setCustomerValue( billingAddress, 'country', 'billing_country' );
		setCustomerValue( billingAddress, 'line1', 'billing_address_1' );
		setCustomerValue( billingAddress, 'line2', 'billing_address_2' );
		setCustomerValue( billingAddress, 'postal_code', 'billing_postcode' );
		setCustomerValue( billingAddress, 'state', 'billing_state' );

		billingDetails.address = billingAddress;
		return billingDetails;
	};

	// Create payment method on submission.
	var paymentMethodGenerated;

	/**
	 * Generates a payment method, saves its ID in a hidden input, and re-submits the form.
	 *
	 * @param {object} $form The jQuery object for the form.
	 * @return {boolean} A flag for the event handler.
	 */
	var onPaymentFormSubmit = function( $form ) {
		// We'll resubmit the form after populating our payment method, so if this is the second time this event
		// is firing we should let the form submission happen.
		if ( paymentMethodGenerated ) {
			paymentMethodGenerated = null;
			return;
		}

		var paymentMethodArgs = {
			type: 'card',
			card: cardElement,
			// eslint-disable-next-line camelcase
			billing_details: loadBillingDetails(),
		};

		stripe.createPaymentMethod( paymentMethodArgs )
			.then( function( result ) {
				var paymentMethod = result.paymentMethod;
				var error = result.error;

				if ( error ) {
					throw error;
				}

				return paymentMethod;
			} )
			.then( function( paymentMethod ) {
				var id = paymentMethod.id;

				// Flag that the payment method has been successfully generated so that we can allow the form
				// submission next time.
				paymentMethodGenerated = true;

				// Populate form with the payment method.
				var paymentMethodInput = document.getElementById( 'wcpay-payment-method' );
				paymentMethodInput.value = id;

				// Re-submit the form.
				$form.submit();
			} );

		// Prevent form submission so that we can fire it once a payment method has been generated.
		return false;
	};

	// Handle the checkout form when WooCommerce Payments is chosen.
	$( 'form.checkout' ).on( 'checkout_place_order_woocommerce_payments', function() {
		return onPaymentFormSubmit( $( this ) );
	} );

	// Handle the Pay for Order form if WooCommerce Payments is chosen.
	$( '#order_review' ).on( 'submit', function() {
		if ( $( '#payment_method_woocommerce_payments' ).is( ':checked' ) ) {
			return onPaymentFormSubmit( $( '#order_review' ) );
		}
	} );
} );
