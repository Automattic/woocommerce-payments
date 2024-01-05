/* global jQuery */

import ReactDOM from 'react-dom';
import React from 'react';
import { __ } from '@wordpress/i18n';
import { dispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { getConfig } from 'utils/order';
import { isAwaitingResponse, isUnderReview } from 'wcpay/disputes/utils';
import RefundConfirmationModal from './refund-confirm-modal';
import CancelConfirmationModal from './cancel-confirm-modal';
import CancelAuthorizationConfirmationModal from './cancel-authorization-confirm-modal';
import TestModeNotice from './test-mode-notice';
import DisputedOrderNoticeHandler from 'wcpay/components/disputed-order-notice';

function disableWooOrderRefundButton( disputeStatus ) {
	const refundButton = document.querySelector( 'button.refund-items' );
	if ( ! refundButton ) {
		return;
	}

	refundButton.disabled = true;

	// Show helpful info in order edit lock icon tooltip.

	let tooltipText = '';
	if ( isAwaitingResponse( disputeStatus ) ) {
		tooltipText = __(
			'Refunds and order editing are disabled during disputes.',
			'woocommerce-payments'
		);
	} else if ( isUnderReview( disputeStatus ) ) {
		tooltipText = __(
			'Refunds and order editing are disabled during an active dispute.',
			'woocommerce-payments'
		);
	} else if ( disputeStatus === 'lost' ) {
		tooltipText = __(
			'Refunds and order editing have been disabled as a result of a lost dispute.',
			'woocommerce-payments'
		);
	}

	jQuery( refundButton )
		.parent()
		.find( '.woocommerce-help-tip' )
		.attr( {
			// jQuery.tipTip uses the title attribute to generate the tooltip.
			title: tooltipText,
			'aria-label': tooltipText,
		} )
		// Regenerate the tipTip tooltip.
		.tipTip();
}

jQuery( function ( $ ) {
	const disableManualRefunds = getConfig( 'disableManualRefunds' ) ?? false;
	const manualRefundsTip = getConfig( 'manualRefundsTip' ) ?? '';
	const chargeId = getConfig( 'chargeId' );
	const testMode = getConfig( 'testMode' );
	// Order and site are both in test mode, or both in live mode.
	// '1' = true, '' = false, null = the order was created before the test mode meta was added, so we assume it matches.
	const orderTestModeMatch = getConfig( 'orderTestModeMatch' ) !== '';

	maybeShowOrderNotices();

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
		//get the original status of the order from post or order data.
		let originalStatus =
			$( 'input#original_post_status' ).val() ||
			$( 'input#original_order_status' ).val();
		//TODO: Remove this after https://github.com/woocommerce/woocommerce/issues/40871 is fixed.
		if ( originalStatus && ! originalStatus.startsWith( 'wc-' ) ) {
			originalStatus = 'wc-' + originalStatus;
		}

		const canRefund = getConfig( 'canRefund' );
		const refundAmount = getConfig( 'refundAmount' );
		const hasOpenAuthorization = getConfig( 'hasOpenAuthorization' );
		if (
			this.value === 'wc-refunded' &&
			originalStatus !== 'wc-refunded'
		) {
			renderRefundConfirmationModal(
				originalStatus,
				canRefund,
				refundAmount
			);
		} else if (
			this.value === 'wc-cancelled' &&
			originalStatus !== 'wc-cancelled'
		) {
			// If order has an uncaptured authorization, confirm
			// that merchant indeed wants to cancel both the order
			// and the authorization.
			if ( hasOpenAuthorization ) {
				renderModal(
					<CancelAuthorizationConfirmationModal
						originalOrderStatus={ originalStatus }
					/>
				);
				return;
			}
			// If it is possible to refund an order, double check that
			// merchants indeed wants to cancel, or if they just want to
			// refund.
			if ( canRefund && refundAmount > 0 ) {
				renderModal(
					<CancelConfirmationModal
						originalOrderStatus={ originalStatus }
					/>
				);
			}
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
		if ( refundAmount <= 0 ) {
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

	function maybeShowOrderNotices() {
		const container = document.querySelector(
			'#wcpay-order-payment-details-container'
		);

		// If the container doesn't exist (WC < 7.9) don't render notices.
		if ( ! container ) {
			return;
		}

		ReactDOM.render(
			<>
				{ testMode && <TestModeNotice /> }

				{ chargeId && orderTestModeMatch && (
					<DisputedOrderNoticeHandler
						chargeId={ chargeId }
						onDisableOrderRefund={ disableWooOrderRefundButton }
					/>
				) }
			</>,
			container
		);
	}
} );
