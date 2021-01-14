/* global wcpaySubscriptionEdit */

const addOption = ( select, value, text ) => {
	const option = document.createElement( 'option' );
	option.value = value;
	option.text = text;
	select.appendChild( option );
	return option;
};

const addWCPayCards = ( {
	gateway,
	table,
	metaKey,
	tokens,
	defaultOptionText,
} ) => {
	const paymentMethodInputId = `_payment_method_meta[${ gateway }][${ table }][${ metaKey }]`;
	const paymentMethodInput = document.getElementById( paymentMethodInputId );
	const validTokenId = tokens.some(
		( token ) => token.tokenId.toString() === paymentMethodInput.value
	);

	// Abort if the input doesn't exist or is already a select element
	if ( ! paymentMethodInput || paymentMethodInput.tagName === 'SELECT' ) {
		return;
	}

	const paymentMethodSelect = document.createElement( 'select' );
	paymentMethodSelect.id = paymentMethodInputId;
	paymentMethodSelect.name = paymentMethodInputId;

	// Add placeholder option if no token matches the existing token ID.
	if ( ! validTokenId ) {
		const defaultOption = addOption(
			paymentMethodSelect,
			'',
			defaultOptionText
		);
		defaultOption.disabled = true;
		defaultOption.selected = true;
	}

	tokens.forEach( ( token ) => {
		addOption( paymentMethodSelect, token.tokenId, token.displayName );
	} );

	if ( validTokenId ) {
		paymentMethodSelect.value = paymentMethodInput.value;
	}

	const formField = paymentMethodInput.parentElement;
	formField.insertBefore( paymentMethodSelect, paymentMethodInput );
	paymentMethodInput.remove();
};

addWCPayCards( wcpaySubscriptionEdit );
