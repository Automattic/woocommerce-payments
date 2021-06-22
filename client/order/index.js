/* global jQuery */

/**
 * Internal dependencies
 */
import { getConfig } from 'utils/order';

jQuery( function ( $ ) {
	const disableManualRefunds = getConfig( 'disableManualRefunds' ) ?? false;
	const manualRefundsTip = getConfig( 'manualRefundsTip' ) ?? '';

	$( '#woocommerce-order-items' ).on(
		'click',
		'button.refund-items',
		function () {
			const $manualRefundButton = $( '.do-manual-refund' );

			if ( disableManualRefunds ) {
				$manualRefundButton.hide();
			} else {
				// Adjust the messaging on the manual refund button.
				$manualRefundButton
					.attr( {
						// Tips are readable through $.data(), but jQuery.tipTip use the title attribute to generate
						// the tooltip.
						title: manualRefundsTip,
					} )
					// Regenerate the tipTip tooltip.
					.tipTip();
			}
		}
	);
} );
