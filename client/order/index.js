/* global jQuery */

import { __ } from '@wordpress/i18n';
import ReactDOM from 'react-dom';
import { dispatch } from '@wordpress/data';
/**
 * Internal dependencies
 */
import { getConfig } from 'utils/order';
import RefundConfirmationModal from './refund-confirm-modal';
import CancelConfirmationModal from './cancel-confirm-modal';
import InlineNotice from '../components/inline-notice';

jQuery( function ( $ ) {
	const disableManualRefunds = getConfig( 'disableManualRefunds' ) ?? false;
	const manualRefundsTip = getConfig( 'manualRefundsTip' ) ?? '';
	const disputeNoticeData = getConfig( 'disputeNoticeData' );

	maybeShowDisputeNotice( disputeNoticeData );

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
		const canRefund = getConfig( 'canRefund' );
		const refundAmount = getConfig( 'refundAmount' );
		if (
			'wc-refunded' === this.value &&
			'wc-refunded' !== originalStatus
		) {
			renderRefundConfirmationModal(
				originalStatus,
				canRefund,
				refundAmount
			);
		} else if (
			'wc-cancelled' === this.value &&
			'wc-cancelled' !== originalStatus
		) {
			if ( ! canRefund || 0 >= refundAmount ) {
				return;
			}
			renderModal(
				<CancelConfirmationModal
					originalOrderStatus={ originalStatus }
				/>
			);
		}
	} );

	function renderRefundConfirmationModal(
		originalStatus,
		canRefund,
		refundAmount
	) {
		if ( ! canRefund ) {
			dispatch( 'core/notices' ).createErrorNotice(
				__( 'Order cannot be refunded', 'woocommerce-payments' )
			);
			return;
		}
		if ( 0 >= refundAmount ) {
			dispatch( 'core/notices' ).createErrorNotice(
				__( 'Invalid Refund Amount', 'woocommerce-payments' )
			);
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

	function maybeShowDisputeNotice( disputeData ) {
		if ( ! disputeData ) {
			return;
		}

		const container = document.querySelector(
			'#wcpay-order-payment-details-container'
		);
		const notice = (
			<InlineNotice
				status="error"
				isDismissible={ false }
				actions={ [
					{
						label: __( 'Respond now', 'woocommerce-payments' ),
						variant: 'secondary',
						onClick: () =>
							( window.location = disputeData.disputeUrl ),
					},
				] }
			>
				<p>
					This order has a chargeback dispute of{ ' ' }
					{ disputeData.amountHtml } labeled as &quot;
					{ disputeData.reason }&quot;. Please respond to this dispute
					before { disputeData.dueBy }.
				</p>
			</InlineNotice>
		);
		ReactDOM.render( notice, container );
	}
} );
