/* global jQuery */

import { __ } from '@wordpress/i18n';
import ReactDOM from 'react-dom';
import { dispatch } from '@wordpress/data';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { getConfig } from 'utils/order';
import RefundConfirmationModal from './refund-confirm-modal';
import CancelConfirmationModal from './cancel-confirm-modal';
import BannerNotice from 'wcpay/components/banner-notice';

jQuery( function ( $ ) {
	const disableManualRefunds = getConfig( 'disableManualRefunds' ) ?? false;
	const manualRefundsTip = getConfig( 'manualRefundsTip' ) ?? '';
	const hasDispute = getConfig( 'hasDispute' ) ?? false;

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

	function renderDisputeNotice() {
		const Notice = () => (
			<BannerNotice status="info" isDismissible={ false }>
				<div style={ { marginBottom: 10 } }>
					This order has a chargeback dispute of $123 for the reason
					of &quot;product damaged&quot;. Please respond to this
					dispute before May 29, 2023.
				</div>
				<Button isSecondary>See dispute details</Button>
			</BannerNotice>
		);

		const noticeWrapper = document.createElement( 'div' );
		document
			.querySelector( '.woocommerce-order-data__meta ' )
			.insertAdjacentElement( 'afterend', noticeWrapper );

		ReactDOM.render( <Notice />, noticeWrapper );
	}

	if ( hasDispute ) {
		renderDisputeNotice();
	}
} );
