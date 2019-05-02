// Setup the Stripe elements when the checkout page is updated.
jQuery( document.body ).on( 'updated_checkout', function() {
	var stripe   = new Stripe( wc_payment_config.publishableKey );
	var elements = stripe.elements();

	// Create a card element.
	var cardElement = elements.create( 'card', {
		hidePostalCode: true
	} );
	cardElement.mount( '#wc-payment-card-element' );

	// Update the validation state based on the element's state.
	cardElement.addEventListener( 'change', function(event) {
		var displayError = document.getElementById( 'wc-payment-errors' );
		if (event.error) {
			displayError.textContent = event.error.message;
		} else {
			displayError.textContent = '';
		}
	} );

	// Create payment token on submission.
	var tokenGenerated;
	jQuery( 'form.checkout' ).on( 'checkout_place_order_woocommerce_payments', function() {
		// We'll resubmit the form after populating our token, so if this is the second time this event is firing we
		// should let the form submission happen.
		if ( tokenGenerated ) {
			return;
		}

		stripe.createToken( cardElement )
			.then( function( result ) {
				var token = result.token;
				var error = result.error;

				if ( error ) {
					throw error;
				}

				return token;
			} )
			.then( function( token ) {
				var id = token.id;

				// Flag that the token has been successfully generated so that we can allow the form submission next
				// time.
				tokenGenerated = true;

				// Populate form with the token.
				var paymentTokenInput   = document.getElementById( 'wc-payment-token' );
				paymentTokenInput.value = id;

				// Re-submit the form.
				jQuery( '.woocommerce-checkout' ).submit();
			} );

		// Prevent form submission so that we can fire it once a token has been generated.
		return false;
	} );
} );
