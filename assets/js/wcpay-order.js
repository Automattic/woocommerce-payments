/* eslint-disable no-var */
/* global jQuery, wcpay_order_config */
( function ( $ ) {
	// eslint-disable-next-line camelcase
	var config = wcpay_order_config || {};

	if ( config.disableManualRefunds ) {
		/**
		 * The script is included in the footer, so all the DOM must already be in place.
		 * This allows us to modify the tip before it gets used on document.ready.
		 */
		$( '.do-manual-refund' ).each( function () {
			var $refundButton = $( this );

			// Disable the button.
			$refundButton
				.addClass( 'disabled' )
				.attr( 'readonly', 'readonly' )
				.on( 'click', function () {
					return false;
				} );

			// Add the right label to indicate why the button is disabled.
			$refundButton.attr( {
				// Tips should be accessible through $.data(), but jQuery.tipTip uses attributes.
				'data-tip': config.manualRefundsTip,
			} );
		} );
	}
} )( jQuery );
