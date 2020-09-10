/* global wcpaySubscriptionEdit */

const addWCPayCards = ( { gateway, table, metaKey, tokens } ) => {
	const paymentMethodInputId = `_payment_method_meta[${ gateway }][${ table }][${ metaKey }]`;
	const paymentMethodInput = document.getElementById( paymentMethodInputId );

	// Abort if the input doesn't exist or is already a select element
	if ( ! paymentMethodInput || 'SELECT' === paymentMethodInput.tagName ) {
		return;
	}

	const paymentMethodSelect = document.createElement( 'select' );
	tokens.forEach( ( token ) => {
		const formattedBrand =
			token.brand.charAt( 0 ).toUpperCase() + token.brand.slice( 1 );
		const option = document.createElement( 'option' );
		option.value = token.paymentMethodId;
		option.text = `${ formattedBrand } •••• ${ token.last4 } (${ token.expiryMonth }/${ token.expiryYear })`;
		paymentMethodSelect.appendChild( option );
	} );
	paymentMethodSelect.id = paymentMethodInputId;
	paymentMethodSelect.name = paymentMethodInputId;
	paymentMethodSelect.value = paymentMethodInput.value;

	const formField = paymentMethodInput.parentElement;
	formField.insertBefore( paymentMethodSelect, paymentMethodInput );
	paymentMethodInput.remove();
};

addWCPayCards( wcpaySubscriptionEdit );
