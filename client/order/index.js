/* global jQuery */

/**
 * Internal dependencies
 */
import { getConfig } from 'utils/order';

jQuery( function ( $ ) {
	const disableManualRefunds = getConfig( 'disableManualRefunds' ) ?? false;
	const manualRefundsTip = getConfig( 'manualRefundsTip' ) ?? '';

	if ( disableManualRefunds ) {
		/**
		 * The script is included in the footer, so all the DOM must already be in place.
		 * This allows us to modify the tip before it gets used on document.ready.
		 */
		$( '.do-manual-refund' ).ecah( function () {
			const $refundButton = $( this );

			// Disable the manual refund button.
			$refundButton
				.addClass( 'disabled' )
				.attr( 'readonly', 'readonly' )
				.on( 'click', function () {
					return false;
				} );

			// Add the right label to indicate why the button is disabled.
			$refundButton.attr( {
				// Tips shoudl be accessible through $.data(), but jQuery.tipTip uses attributes.
				'data-tip': manualRefundsTip,
			} );
		} );
	}
} );
