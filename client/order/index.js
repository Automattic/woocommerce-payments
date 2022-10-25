/* global jQuery */

import { __ } from '@wordpress/i18n';
import ReactDOM from 'react-dom';
/**
 * Internal dependencies
 */
import { getConfig } from 'utils/order';
import RefundConfirmationModal from './refund-confirm-modal';

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

	$( 'select#order_status' ).on( 'change', function () {
		const originalStatus = $( 'input#original_post_status' ).val();
		const refundAmount = getConfig( 'remainingRefundAmount' );
		if ( 'wc-refunded' === this.value &&  'wc-refunded' !== originalStatus ) {
			if( 0 >= refundAmount ) {
				alert( __( 'Invalid Refund Amount', 'woocommerce-payments' ) );
				return;
			}
			const container = document.createElement( 'div' );
			container.id = 'wcpay-refund-confirm-container';
			document.body.appendChild( container );
			ReactDOM.render(
				<RefundConfirmationModal
					orderStatus={ originalStatus }
					refundAmount={ refundAmount }
					refundedAmount={ getConfig( 'refundedAmount' ) }
					currencyCode={ getConfig( 'orderCurrency' ) }
				/>,
				container
			);
		}
	} );
} );
