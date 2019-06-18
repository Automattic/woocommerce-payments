// Setup the Stripe elements when the checkout page is updated.
jQuery( document.body ).on( 'updated_checkout', function() {
	var stripe   = new Stripe( wc_payment_config.publishableKey, {
		stripeAccount: wc_payment_config.accountId
	} );
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

	// Create payment source on submission.
	var sourceGenerated;
	jQuery( 'form.checkout' ).on( 'checkout_place_order_woocommerce_payments', function() {
		// We'll resubmit the form after populating our source, so if this is the second time this event is firing we
		// should let the form submission happen.
		if ( sourceGenerated ) {
			return;
		}

		stripe.createSource( cardElement )
			.then( function( result ) {
				var source = result.source;
				var error = result.error;

				if ( error ) {
					throw error;
				}

				return source;
			} )
			.then( function( source ) {
				var id = source.id;

				// Flag that the source has been successfully generated so that we can allow the form submission next
				// time.
				sourceGenerated = true;

				// Populate form with the source.
				var paymentSourceInput   = document.getElementById( 'wc-payment-source' );
				paymentSourceInput.value = id;

				// Re-submit the form.
				jQuery( '.woocommerce-checkout' ).submit();
			} );

		// Prevent form submission so that we can fire it once a source has been generated.
		return false;
	} );
} );
