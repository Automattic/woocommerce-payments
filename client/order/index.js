/* global jQuery */

/**
 * Internal dependencies
 */
import { getConfig } from 'utils/order';

jQuery( function ( $ ) {
	const disableManualRefunds = getConfig( 'disableManualRefunds' ) ?? false;
	const manualRefundsTip = getConfig( 'manualRefundsTip' ) ?? '';

	/**
	 * The script is included in the footer, so all the DOM must already be in place.
	 * This allows us to modify the tip before it gets used on document.ready.
	 */
	$( '.do-manual-refund' ).each( function () {
		const $refundButton = $( this );

		if ( disableManualRefunds ) {
			$refundButton.hide();
		} else {
			// Adjust the messaging on the manual refund button.
			$refundButton
				.attr( {
					// Tips are readable through $.data(), but jQuery.tipTip use the title attribute to generate
					// the tooltip.
					title: manualRefundsTip,
				} )
				// Regenerate the tipTip tooltip.
				.tipTip();
		}
	} );
} );
