const showLinkButton = ( linkAutofill ) => {
	// Display StripeLink button if email field is prefilled.
	const billingEmailInput = document.getElementById( 'billing_email' );
	if ( billingEmailInput.value !== '' ) {
		const linkButtonTop =
			billingEmailInput.offsetTop +
			( billingEmailInput.offsetHeight - 40 ) / 2;
		const stripeLinkButton = document.querySelector(
			'.wcpay-stripelink-modal-trigger'
		);
		stripeLinkButton.style.display = 'block';
		stripeLinkButton.style.top = `${ linkButtonTop }px`;
	}

	// Handle StripeLink button click.
	const stripeLinkButton = document.querySelector(
		'.wcpay-stripelink-modal-trigger'
	);
	stripeLinkButton.addEventListener( 'click', ( event ) => {
		event.preventDefault();
		// Trigger modal.
		linkAutofill.launch( { email: billingEmailInput.value } );
	} );
};

export const autofill = ( event, options ) => {
	const { billingAddress, shippingAddress } = event.value;
	const fillWith = options.fill_field_method
		? options.fill_field_method
		: ( address, nodeId, key ) => {
				if ( document.getElementById( nodeId ) !== null ) {
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
		fillWith( shippingAddress, options.shipping_fields.country, 'country' );
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
		const billingCountryStateSelects = document.querySelectorAll(
			'#billing_country, #billing_state, #shipping_country, #shipping_state'
		);
		billingCountryStateSelects.forEach( ( select ) =>
			select.dispatchEvent( new Event( 'change' ) )
		);
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
		fillWith( billingAddress, options.billing_fields.country, 'country' );
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

		const billingCountryStateSelects = document.querySelectorAll(
			'#billing_country, #billing_state, #shipping_country, #shipping_state'
		);
		billingCountryStateSelects.forEach( ( select ) =>
			select.dispatchEvent( new Event( 'change' ) )
		);
		fillWith( billingAddress, options.billing_fields.state, 'state' );
		fillWith(
			billingAddress,
			options.billing_fields.postal_code,
			'postal_code'
		);
	}
	const billingCountryStateSelects = document.querySelectorAll(
		'#billing_country, #billing_state, #shipping_country, #shipping_state'
	);
	billingCountryStateSelects.forEach( ( select ) =>
		select.dispatchEvent( new Event( 'change' ) )
	);
};

const enableStripeLinkPaymentMethod = ( options ) => {
	if ( ! document.getElementById( options.emailId ) ) {
		return;
	}
	const api = options.api;
	const linkAutofill = api.getStripe().linkAutofillModal( options.elements );

	document
		.getElementById( options.emailId )
		.addEventListener( 'keyup', ( event ) => {
			linkAutofill.launch( { email: event.target.value } );
		} );

	const showButton = options.show_button
		? options.show_button
		: showLinkButton;
	showButton( linkAutofill );

	linkAutofill.on( 'autofill', ( event ) => {
		autofill( event, options );
	} );
};

export default enableStripeLinkPaymentMethod;
