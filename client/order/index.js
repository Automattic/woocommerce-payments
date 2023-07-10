/* global jQuery */

import { __, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import ReactDOM from 'react-dom';
import { dispatch } from '@wordpress/data';
import moment from 'moment';

/**
 * Internal dependencies
 */
import { getConfig } from 'utils/order';
import RefundConfirmationModal from './refund-confirm-modal';
import CancelConfirmationModal from './cancel-confirm-modal';
import BannerNotice from 'wcpay/components/banner-notice';
import { formatExplicitCurrency } from 'utils/currency';
import { reasons } from 'wcpay/disputes/strings';
import './style.scss';

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

		const now = moment();
		const dueBy = moment( disputeData.dueBy );
		const countdownDays = dueBy.diff( now, 'days', true );

		const amountFormatted = formatExplicitCurrency(
			disputeData.amount,
			disputeData.currency
		);

		let urgency = 'warning';
		let buttonLabel = __( 'Respond now', 'woocommerce-payments' );
		let suffix = sprintf(
			// Translators: %d is the number of days left to respond to the dispute.
			__( '(%d days left)', 'woocommerce-payments' ),
			countdownDays
		);
		// If the dispute is due within 72 hours, we want to highlight it as urgent/red.
		if ( 3 > countdownDays ) {
			urgency = 'error';
		}
		if ( 1 > countdownDays ) {
			urgency = 'error';
			buttonLabel = __( 'Respond today', 'woocommerce-payments' );
			suffix = __( '(Last day today)', 'woocommerce-payments' );
		}

		const notice = (
			<BannerNotice
				status={ urgency }
				isDismissible={ false }
				actions={ [
					{
						label: buttonLabel,
						variant: 'secondary',
						onClick: () => {
							// Handle tracks event here.
							// wcpayTracks.recordEvent( wcpayTracks.events.xxx, {
							// 	due_by_days: countdownDays,
							// } );
							window.location = disputeData.url;
						},
					},
				] }
			>
				<strong>
					{ sprintf(
						// Translators: %1$s is the formatted dispute amount, %2$s is the dispute reason, %3$s is the due date.
						__(
							'Please resolve the dispute on this order for %1$s labeled "%2$s" by %3$s.',
							'woocommerce-payments'
						),
						amountFormatted,
						reasons[ disputeData.reason ].display,
						dateI18n( 'M j, Y', dueBy.local().toISOString() )
					) }{ ' ' }
					{ suffix }
				</strong>
			</BannerNotice>
		);
		ReactDOM.render( notice, container );
	}
} );
