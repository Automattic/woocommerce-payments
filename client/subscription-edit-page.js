/* global wcpaySubscriptionEdit */
/* global jQuery */
/* global woocommerce_admin */

const addOption = ( select, value, text ) => {
	const option = document.createElement( 'option' );
	option.value = value;
	option.text = text;
	select.appendChild( option );
	return option;
};

const paymentMethodInputId = CSS.escape(
	`_payment_method_meta[${ wcpaySubscriptionEdit.gateway }]` +
		`[${ wcpaySubscriptionEdit.table }][${ wcpaySubscriptionEdit.metaKey }]`
);

// TODO: Remove admin payment method JS hack for Subscriptions <= 3.0.7 when we drop support for those versions. Start
const addWCPayCards = ( { tokens, defaultOptionText } ) => {
	const paymentMethodInput = document.getElementById( paymentMethodInputId );
	const validTokenId = tokens.some(
		( token ) => token.tokenId.toString() === paymentMethodInput.value
	);

	// Abort if the input doesn't exist or is already a select element
	if ( ! paymentMethodInput || 'SELECT' === paymentMethodInput.tagName ) {
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

if ( wcpaySubscriptionEdit.shouldAddWCPayCards ) {
	addWCPayCards( wcpaySubscriptionEdit );
}
// TODO: Remove admin payment method JS hack for Subscriptions <= 3.0.7 when we drop support for those versions. End

jQuery( function ( $ ) {
	//Loads the saved credit cards after customer is selected
	//when editing/creating a subscription
	//needs to be done with jquery because
	$( '#woocommerce-subscription-data #customer_user' ).change( function () {
		const data = new FormData();

		data.append( 'action', 'wcs_get_saved_credit_cards' );
		data.append( 'customer', this.options[ this.selectedIndex ].value );
		data.append( 'nonce', wcpaySubscriptionEdit.get_cards_tokens_nonce );

		fetch( woocommerce_admin.ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			body: data,
		} )
			.then( ( response ) => response.json() )
			.then( ( cards ) => {
				const cardsSelect = document.querySelector(
					'#' + paymentMethodInputId
				);

				cardsSelect.innerHTML = '';

				addOption(
					cardsSelect,
					'',
					wcpaySubscriptionEdit.defaultOptionText
				);

				for ( const card of cards ) {
					addOption( cardsSelect, card.tokenId, card.displayName );
				}
			} );
	} );
} );
