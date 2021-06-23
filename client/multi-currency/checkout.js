/* global jQuery */

// We need to use jQuery here because WC Core events use jQuery's event system,
// which cannot be used with the vanilla JS functions and native event system.
jQuery( function ( $ ) {
	let checkoutCountry = $( 'select#billing_country' ).val();

	$( document.body ).on( 'updated_checkout', () => {
		const country = $( 'select#billing_country' ).val();

		if ( checkoutCountry !== country ) {
			checkoutCountry = country;
			$( document.body ).trigger( 'wc_fragment_refresh' );
		}
	} );
} );
