/* global jQuery */

import { __, _n, sprintf } from '@wordpress/i18n';
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
import wcpayTracks from 'tracks';
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

		// If the container doesn't exist (WC < 7.9), return.
		if ( ! container ) {
			return;
		}

		const now = moment();
		const dueBy = moment.unix( disputeData.dueBy );
		const countdownDays = Math.floor( dueBy.diff( now, 'days', true ) );

		// If the dispute is due in the past, we don't want to show the notice.
		if ( now.isAfter( dueBy ) ) {
			return;
		}

		const amountFormatted = formatExplicitCurrency(
			disputeData.amount,
			disputeData.currency
		);

		let urgency = 'warning';
		let buttonLabel = __( 'Respond now', 'woocommerce-payments' );
		let title = sprintf(
			// Translators: %1$s is the formatted dispute amount, %2$s is the dispute reason, %3$s is the due date.
			__(
				'This order has a chargeback dispute of %1$s labeled as "%2$s". Please respond to this dispute before %3$s.',
				'woocommerce-payments'
			),
			amountFormatted,
			reasons[ disputeData.reason ].display,
			dateI18n( 'M j, Y', dueBy.local().toISOString() )
		);
		let suffix = '';

		// If the dispute is due within 7 days, use different wording.
		if ( 7 > countdownDays ) {
			title = sprintf(
				// Translators: %1$s is the formatted dispute amount, %2$s is the dispute reason, %3$s is the due date.
				__(
					'Please resolve the dispute on this order for %1$s labeled "%2$s" by %3$s.',
					'woocommerce-payments'
				),
				amountFormatted,
				reasons[ disputeData.reason ].display,
				dateI18n( 'M j, Y', dueBy.local().toISOString() )
			);
			suffix = sprintf(
				// Translators: %d is the number of days left to respond to the dispute.
				_n(
					'(%s day left)',
					'(%s days left)',
					countdownDays,
					'woocommerce-payments'
				),
				countdownDays
			);
		}

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
							wcpayTracks.recordEvent(
								wcpayTracks.events
									.ORDER_DISPUTE_NOTICE_BUTTON_CLICK,
								{
									due_by_days: parseInt( countdownDays, 10 ),
								}
							);
							window.location = disputeData.url;
						},
					},
				] }
			>
				<strong>
					{ title } { suffix }
				</strong>
			</BannerNotice>
		);
		ReactDOM.render( notice, container );
	}
} );
