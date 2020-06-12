/* global jQuery */

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from '../constants.js';
import { getConfig } from './../utils.js';
import WCPayAPI from './../api';
import card from '@wordpress/components/build/card';

jQuery( function( $ ) {
	// Create an API object, which will be used throughout the checkout.
	const api = new WCPayAPI( {
		publishableKey: getConfig( 'publishableKey' ),
		accountId: getConfig( 'accountId' ),
	} );
	const elements = api.getStripe().elements();

	// In the future this object will be loaded with customer information through `wp_localize_script`.
	const preparedCustomerData = {};

	// Create a card element.
	const cardElement = elements.create( 'card', {
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
		const displayError = $( '#wcpay-errors' );
		if ( event.error ) {
			displayError.html( '<ul class="woocommerce-error"><li /></ul>' )
				.find( 'li' ).text( event.error.message );
		} else {
			displayError.empty();
		}
	} );

	// Show error notice at top of checkout form.
	const showError = ( errorMessage ) => {
		const messageWrapper = '<ul class="woocommerce-error" role="alert">' + errorMessage + '</ul>';
		const $container = $( '.woocommerce-notices-wrapper, form.checkout' ).first();

		if ( ! $container.length ) {
			return;
		}

		// Adapted from WooCommerce core @ ea9aa8c, assets/js/frontend/checkout.js#L514-L529
		$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
		$container.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + messageWrapper + '</div>' );
		$container.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();

		let scrollElement = $( '.woocommerce-NoticeGroup-checkout' );
		if ( ! scrollElement.length ) {
			scrollElement = $container;
		}

		$.scroll_to_notices( scrollElement );
		$( document.body ).trigger( 'checkout_error' );
	};

	// Create payment method on submission.
	let paymentMethodGenerated;

	/**
	 * Generates a payment method, saves its ID in a hidden input, and re-submits the form.
	 *
	 * @param {object} $form The jQuery object for the form.
	 * @return {boolean} A flag for the event handler.
	 */
	const handleOnPaymentFormSubmit = ( $form ) => {
		// We'll resubmit the form after populating our payment method, so if this is the second time this event
		// is firing we should let the form submission happen.
		if ( paymentMethodGenerated ) {
			paymentMethodGenerated = null;
			return;
		}

		// Block UI to indicate processing and avoid duplicate submission.
		$form.addClass( 'processing' ).block( {
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6,
			},
		} );

		const request = api.generatePaymentMethodFromCard( {
			card: cardElement,
		} );

		// Populate the necessary billing details.
		request.setBillingDetail( 'name', ( $( '#billing_first_name' ).val() + ' ' + $( '#billing_last_name' ).val() ).trim() );
		request.setBillingDetail( 'email', $( '#billing_email' ).val() );
		request.setBillingDetail( 'phone', $( '#billing_phone' ).val() );
		request.setAddressDetail( 'city', $( '#billing_city' ).val() );
		request.setAddressDetail( 'country', $( '#billing_country' ).val() );
		request.setAddressDetail( 'line1', $( '#billing_address_1' ).val() );
		request.setAddressDetail( 'line2', $( '#billing_address_2' ).val() );
		request.setAddressDetail( 'postal_code', $( '#billing_postcode' ).val() );
		request.setAddressDetail( 'state', $( '#billing_state' ).val() );

		request.send()
			.then( function( result ) {
				const paymentMethod = result.paymentMethod;
				const error = result.error;

				if ( error ) {
					throw error;
				}

				return paymentMethod;
			} )
			.then( function( paymentMethod ) {
				const id = paymentMethod.id;

				// Flag that the payment method has been successfully generated so that we can allow the form
				// submission next time.
				paymentMethodGenerated = true;

				// Populate form with the payment method.
				const paymentMethodInput = document.getElementById( 'wcpay-payment-method' );
				paymentMethodInput.value = id;

				// Re-submit the form.
				$form.removeClass( 'processing' ).submit();
			} )
			.catch( function( error ) {
				$form.removeClass( 'processing' ).unblock();
				showError( error.message );
			} );

		// Prevent form submission so that we can fire it once a payment method has been generated.
		return false;
	};

	// Handle the checkout form when WooCommerce Payments is chosen.
	$( 'form.checkout' ).on( 'checkout_place_order_' + PAYMENT_METHOD_NAME, function() {
		return handleOnPaymentFormSubmit( $( this ) );
	} );

	// Handle the Pay for Order form if WooCommerce Payments is chosen.
	$( '#order_review' ).on( 'submit', function() {
		if ( $( '#payment_method_' + PAYMENT_METHOD_NAME ).is( ':checked' ) ) {
			return handleOnPaymentFormSubmit( $( '#order_review' ) );
		}
	} );

	window.addEventListener( 'hashchange', function( event ) {
		const confirmation = api.confirmIntent( event.newURL );

		// Boolean `true` means that there is nothing to confirm.
		if ( true === confirmation ) {
			return;
		}

		// Cleanup the URL.
		// https://stackoverflow.com/questions/1397329/
		// how-to-remove-the-hash-from-window-location-url-with-javascript-without-page-r/
		// 5298684#5298684
		history.replaceState( '', document.title, window.location.pathname + window.location.search );

		confirmation
			.then( ( redirectUrl ) => {
				console.log( redirectUrl );
				window.location = redirectUrl;
			} )
			.catch( ( error ) => {
				$( 'form.checkout' ).removeClass( 'processing' ).unblock();

				let errorMessage = error.message;

				// If this is a generic error, we probably don't want to display the error message to the user,
				// so display a generic message instead.
				if ( error instanceof Error ) {
					// eslint-disable-next-line camelcase
					errorMessage = getConfig( 'genericErrorMessage' );
				}

				showError( errorMessage );
			} );
	} );
} );
