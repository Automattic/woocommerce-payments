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
		const option = document.createElement( 'option' );
		option.value = token.tokenId;
		option.text = token.displayName;
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
