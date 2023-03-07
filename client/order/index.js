/* global jQuery */

import { __ } from '@wordpress/i18n';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import { getConfig } from 'utils/order';
import RefundConfirmationModal from './refund-confirm-modal';
import CancelConfirmationModal from './cancel-confirm-modal';

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
		if (
			'wc-refunded' === this.value &&
			'wc-refunded' !== originalStatus
		) {
			renderRefundConfirmationModal( originalStatus );
		} else if (
			'wc-cancelled' === this.value &&
			'wc-cancelled' !== originalStatus
		) {
			renderModal(
				<CancelConfirmationModal
					originalOrderStatus={ originalStatus }
				/>
			);
		}
	} );

	function renderRefundConfirmationModal( originalStatus ) {
		const canRefund = getConfig( 'canRefund' );
		const refundAmount = getConfig( 'refundAmount' );
		if ( ! canRefund ) {
			alert( __( 'Order cannot be refunded', 'woocommerce-payments' ) );
			return;
		}
		if ( 0 >= refundAmount ) {
			alert( __( 'Invalid Refund Amount', 'woocommerce-payments' ) );
			return;
		}
		renderModal(
			<RefundConfirmationModal
				orderStatus={ originalStatus }
				refundAmount={ refundAmount }
				formattedRefundAmount={ getConfig( 'formattedRefundAmount' ) }
				refundedAmount={ getConfig( 'refundedAmount' ) }
			/>
		);
	}

	function renderModal( modalToRender ) {
		const container = document.createElement( 'div' );
		container.id = 'wcpay-orderstatus-confirm-container';
		document.body.appendChild( container );
		ReactDOM.render( modalToRender, container );
	}
} );
