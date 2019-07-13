/* global jQuery, Stripe, wc_payment_config */
jQuery( function() {
	'use strict';

	var stripe   = new Stripe( wc_payment_config.publishableKey, {
		stripeAccount: wc_payment_config.accountId
	} );
	var elements = stripe.elements();

	// Create a card element.
	var cardElement = elements.create( 'card', {
		hidePostalCode: true
	} );

	// Only attempt to mount the card element once that section of the page has loaded. We can use the updated_checkout
	// event for this. This part of the page can also reload based on changes to checkout details, so we call unmount
	// first to ensure the card element is re-mounted correctly.
	jQuery( document.body ).on( 'updated_checkout', function() {
		cardElement.unmount();
		cardElement.mount( '#wc-payment-card-element' );
	} );

	// Update the validation state based on the element's state.
	cardElement.addEventListener( 'change', function(event) {
		var displayError = document.getElementById( 'wc-payment-errors' );
		if (event.error) {
			displayError.textContent = event.error.message;
		} else {
			displayError.textContent = '';
		}
	} );

	// Create payment intention on submission.
	var paymentIntentionGenerated;
	jQuery( 'form.checkout' ).on( 'checkout_place_order_woocommerce_payments', function() {
		// We'll resubmit the form after populating our payment method, so if this is the second time this event
		// is firing we should let the form submission happen.
		if ( paymentIntentionGenerated ) {
			return;
		}

		stripe.createPaymentMethod( 'card', cardElement )
			.then( function( result ) {
				var paymentMethod = result.paymentMethod;
				var error = result.error;

				if ( error ) {
					throw error;
				}

				return paymentMethod;
			} )
			.then( function( paymentMethod ) {
				var paymentMethodId = paymentMethod.id;

				return jQuery.post(
					wc_payment_config.ajaxurl,
					{
						action: 'create_payment_intention',
						wc_payment_method_id: paymentMethodId,
					}
				);
			} )
			.then( function( paymentIntentionResult ) {
				if ( ! paymentIntentionResult.success ) {
					var error = paymentIntentionResult.data;
					throw error;
				}

				var paymentIntention = paymentIntentionResult.data;

				if ( false === paymentIntention.requires_action ) {
					// Populate form with the payment intention ID
					var paymentIntentionInput   = document.getElementById( 'wc-payment-intention-id' );
					paymentIntentionInput.value = paymentIntention.payment_intention_id;

					// Flag that the payment method has been successfully generated so that we can allow the form
					// submission next time.
					paymentIntentionGenerated = true;

					// Re-submit the form.
					jQuery( '.woocommerce-checkout' ).submit();
				} else {
					return stripe.handleCardAction(
						paymentIntention.payment_intention_client_secret
					);
				}
			} )
			.then( function( handleCardActionResult ) {
				if ( ! handleCardActionResult ) {
					// No card action needs to be handled.
					return;
				}

				var error = handleCardActionResult.error;
				if ( error ) {
					throw error;
				}

				// Populate form with the payment intention ID
				var paymentIntentionInput = document.getElementById( 'wc-payment-intention-id' );
				paymentIntentionInput.value = handleCardActionResult.paymentIntent.id;

				// Flag that the payment method has been successfully generated so that we can allow the form
				// submission next time.
				paymentIntentionGenerated = true;

				// Re-submit the form.
				jQuery( '.woocommerce-checkout' ).submit();
			} )
			.catch( function( error ) {
				var displayError = document.getElementById( 'wc-payment-errors' );
				displayError.textContent = error.message;

				console.log('Error processing payment:', error);
			} );

		// Prevent form submission so that we can fire it once a payment method has been generated.
		return false;
	} );
} );
