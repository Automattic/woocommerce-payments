/* global jQuery, wcpayPaymentRequestParams, tb_show */
jQuery( ( $ ) => {
	// Remove Thickbox wrapper once it's closed.
	$( document.body ).on( 'thickbox:removed', () => {
		$( '.TB_wrapper' ).remove();
	} );
} );

export const displayRedirectDialog = ( paymentRequestType ) => {
	tb_show(
		wcpayPaymentRequestParams.site_url,
		'#TB_inline?width=400&inlineId=payment-request-redirect-dialog'
	);

	// Refactor positioning of Thickbox to be mobile-friendly by displaying it
	// inside a special container.
	//
	// For reference, see `tb_show` and `tb_position` in WP core thickbox.js.
	// Note: We shouldn't change the CSS for the default Thickbox elements,
	// otherwise it may break the styling in other pages.
	jQuery( 'body' ).append( '<div class="TB_wrapper"></div>' );
	jQuery( '#TB_window' ).css( {
		position: 'initial',
		width: '100%',
		height: '',
		'margin-left': '',
		'margin-top': '',
		'max-width': '400px',
	} );
	jQuery( '#TB_ajaxContent' ).css( {
		width: '',
		height: '',
	} );
	jQuery( '#TB_title' ).css( {
		position: 'relative',
	} );
	jQuery( '#TB_window' ).appendTo( '.TB_wrapper' );

	// Replace dialog text with specific payment request type "Apple Pay" or "Google Pay".
	if ( 'payment_request_api' !== paymentRequestType ) {
		jQuery( '#TB_ajaxContent .payment-request-type' ).html(
			'apple_pay' === paymentRequestType ? 'Apple Pay' : 'Google Pay'
		);
	}
};
