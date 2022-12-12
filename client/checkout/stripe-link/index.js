/* global jQuery */

const enableStripeLinkPaymentMethod = ( options ) => {
	if ( ! document.getElementById( options.emailId ) ) {
		return;
	}
	const api = options.api;
	const linkAutofill = api.getStripe().linkAutofillModal( options.elements );

	// Handle StripeLink button click.
	jQuery( '#wcpay-stripe-link-button' ).on( 'click', ( event ) => {
		event.preventDefault();
		jQuery( '#payment_method_woocommerce_payments_link' ).click();
		document
			.getElementById( options.emailId )
			.addEventListener( 'keyup', ( e ) => {
				linkAutofill.launch( { email: e.target.value } );
			} );
		const emailValue = jQuery( `#${ options.emailId }` ).val();
		// Trigger modal.
		if ( '' === emailValue ) {
			options.showError(
				'Please enter your email address to checkout with Link.'
			);
			jQuery( `#${ options.emailId }` ).focus();
		} else {
			linkAutofill.launch( { email: emailValue } );
		}
	} );

	linkAutofill.on( 'autofill', ( event ) => {
		const { billingAddress, shippingAddress } = event.value;
		const fillWith = options.fill_field_method
			? options.fill_field_method
			: ( address, nodeId, key ) => {
					if ( null !== document.getElementById( nodeId ) ) {
						document.getElementById( nodeId ).value =
							address.address[ key ];
					}
			  };

		if ( options.complete_shipping() ) {
			const shippingNames = shippingAddress.name.split( / (.*)/s, 2 );
			shippingAddress.address.last_name = shippingNames[ 1 ];
			shippingAddress.address.first_name = shippingNames[ 0 ];

			fillWith( shippingAddress, options.shipping_fields.line1, 'line1' );
			fillWith( shippingAddress, options.shipping_fields.line2, 'line2' );
			fillWith( shippingAddress, options.shipping_fields.city, 'city' );
			fillWith(
				shippingAddress,
				options.shipping_fields.country,
				'country'
			);
			fillWith(
				shippingAddress,
				options.shipping_fields.first_name,
				'first_name'
			);
			fillWith(
				shippingAddress,
				options.shipping_fields.last_name,
				'last_name'
			);
			jQuery(
				'#billing_country, #billing_state, #shipping_country, #shipping_state'
			).trigger( 'change' );
			fillWith( shippingAddress, options.shipping_fields.state, 'state' );
			fillWith(
				shippingAddress,
				options.shipping_fields.postal_code,
				'postal_code'
			);
		}

		if ( options.complete_billing() ) {
			const billingNames = billingAddress.name.split( / (.*)/s, 2 );
			billingAddress.address.last_name = billingNames[ 1 ];
			billingAddress.address.first_name = billingNames[ 0 ];

			fillWith( billingAddress, options.billing_fields.line1, 'line1' );
			fillWith( billingAddress, options.billing_fields.line2, 'line2' );
			fillWith( billingAddress, options.billing_fields.city, 'city' );
			fillWith(
				billingAddress,
				options.billing_fields.country,
				'country'
			);
			fillWith(
				billingAddress,
				options.billing_fields.first_name,
				'first_name'
			);
			fillWith(
				billingAddress,
				options.billing_fields.last_name,
				'last_name'
			);

			jQuery(
				'#billing_country, #billing_state, #shipping_country, #shipping_state'
			).trigger( 'change' );
			fillWith( billingAddress, options.billing_fields.state, 'state' );
			fillWith(
				billingAddress,
				options.billing_fields.postal_code,
				'postal_code'
			);
		}
		jQuery(
			'#billing_country, #billing_state, #shipping_country, #shipping_state'
		).trigger( 'change' );
	} );
};

export default enableStripeLinkPaymentMethod;
