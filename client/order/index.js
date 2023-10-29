/* global jQuery */

import moment from 'moment';
import ReactDOM from 'react-dom';
import React, { useEffect } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import { dispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { getConfig } from 'utils/order';
import RefundConfirmationModal from './refund-confirm-modal';
import CancelConfirmationModal from './cancel-confirm-modal';
import InlineNotice from 'components/inline-notice';
import { formatExplicitCurrency } from 'utils/currency';
import { reasons } from 'wcpay/disputes/strings';
import { getDetailsURL } from 'wcpay/components/details-link';
import {
	isAwaitingResponse,
	isInquiry,
	isUnderReview,
} from 'wcpay/disputes/utils';
import { useCharge } from 'wcpay/data';
import wcpayTracks from 'tracks';
import './style.scss';

jQuery( function ( $ ) {
	const disableManualRefunds = getConfig( 'disableManualRefunds' ) ?? false;
	const manualRefundsTip = getConfig( 'manualRefundsTip' ) ?? '';
	const chargeId = getConfig( 'chargeId' );

	maybeShowDisputeNotice();

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
			if ( ! canRefund || refundAmount <= 0 ) {
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

	function maybeShowDisputeNotice() {
		const container = document.querySelector(
			'#wcpay-order-payment-details-container'
		);

		// If the container doesn't exist (WC < 7.9), or the charge ID isn't present, don't render the notice.
		if ( ! container || ! chargeId ) {
			return;
		}

		ReactDOM.render(
			<DisputeNoticeWrapper chargeId={ chargeId } />,
			container
		);
	}
} );

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

const DisputeNoticeWrapper = ( { chargeId } ) => {
	const { data: charge } = useCharge( chargeId );
	const disputeDetailsUrl = getDetailsURL( chargeId, 'transactions' );

	// Disable the refund button if there's an active dispute.
	useEffect( () => {
		const { dispute } = charge;
		if ( ! charge?.dispute ) {
			return;
		}
		// Refunds are only allowed if the dispute is an inquiry or if it's won.
		const isRefundable =
			isInquiry( dispute ) || [ 'won' ].includes( dispute.status );
		if ( ! isRefundable ) {
			disableWooOrderRefundButton( dispute.status );
		}
	}, [ charge ] );

	const { dispute } = charge;
	if ( ! charge?.dispute ) {
		return null;
	}

	// Special case the "under review" notice which is much simpler.
	// (And return early.)
	if ( isUnderReview( dispute.status ) ) {
		return <DisputeUnderReviewNotice />;
	}

	// Only show the notice if the dispute is awaiting a response.
	if ( ! isAwaitingResponse( dispute.status ) ) {
		return null;
	}

	// Bail if we don't have due_by for whatever reason.
	if ( ! dispute.evidence_details?.due_by ) {
		return null;
	}

	const now = moment();
	const dueBy = moment.unix( dispute.evidence_details?.due_by );

	// If the dispute is due in the past, don't show notice.
	if ( ! now.isBefore( dueBy ) ) {
		return null;
	}

	return (
		<DisputeNeedsResponseNotice
			chargeId={ chargeId }
			disputeReason={ dispute.reason }
			formattedAmount={ formatExplicitCurrency(
				dispute.amount,
				dispute.currency
			) }
			isPreDisputeInquiry={ isInquiry( dispute ) }
			dueBy={ dueBy }
			countdownDays={ Math.floor( dueBy.diff( now, 'days', true ) ) }
			disputeDetailsUrl={ disputeDetailsUrl }
		/>
	);
};

const DisputeNeedsResponseNotice = ( {
	disputeReason,
	formattedAmount,
	isPreDisputeInquiry,
	dueBy,
	countdownDays,
	disputeDetailsUrl,
} ) => {
	useEffect( () => {
		wcpayTracks.recordEvent( 'wcpay_order_dispute_notice_view', {
			is_inquiry: isPreDisputeInquiry,
			dispute_reason: disputeReason,
			due_by_days: countdownDays,
		} );
	}, [ isPreDisputeInquiry, disputeReason, countdownDays ] );

	const titleStrings = {
		// Translators: %1$s is the formatted dispute amount, %2$s is the dispute reason, %3$s is the due date.
		dispute_default: __(
			// eslint-disable-next-line max-len
			'This order has been disputed in the amount of %1$s. The customer provided the following reason: %2$s. Please respond to this dispute before %3$s.',
			'woocommerce-payments'
		),
		// Translators: %1$s is the formatted dispute amount, %2$s is the dispute reason, %3$s is the due date.
		inquiry_default: __(
			// eslint-disable-next-line max-len
			'The card network involved in this order has opened an inquiry into the transaction with the following reason: %2$s. Please respond to this inquiry before %3$s, just like you would for a formal dispute.',
			'woocommerce-payments'
		),
		// Translators: %1$s is the formatted dispute amount, %2$s is the dispute reason, %3$s is the due date.
		dispute_urgent: __(
			'Please resolve the dispute on this order for %1$s labeled "%2$s" by %3$s.',
			'woocommerce-payments'
		),
		// Translators: %1$s is the formatted dispute amount, %2$s is the dispute reason, %3$s is the due date.
		inquiry_urgent: __(
			'Please resolve the inquiry on this order for %1$s labeled "%2$s" by %3$s.',
			'woocommerce-payments'
		),
	};

	let buttonLabel = __( 'Respond now', 'woocommerce-payments' );
	let suffix = '';

	let titleText = isPreDisputeInquiry
		? titleStrings.inquiry_default
		: titleStrings.dispute_default;

	// If the dispute is due within 7 days, adjust wording and highlight urgency.
	if ( countdownDays < 7 ) {
		titleText = isPreDisputeInquiry
			? titleStrings.inquiry_urgent
			: titleStrings.dispute_urgent;

		suffix = sprintf(
			// Translators: %s is the number of days left to respond to the dispute.
			_n(
				'(%s day left)',
				'(%s days left)',
				countdownDays,
				'woocommerce-payments'
			),
			countdownDays
		);
	}

	const title = sprintf(
		titleText,
		formattedAmount,
		reasons[ disputeReason ].display,
		dateI18n( 'M j, Y', dueBy.local().toISOString() )
	);

	if ( countdownDays < 1 ) {
		buttonLabel = __( 'Respond today', 'woocommerce-payments' );
		suffix = __( '(Last day today)', 'woocommerce-payments' );
	}

	return (
		<InlineNotice
			status={ countdownDays < 3 ? 'error' : 'warning' }
			isDismissible={ false }
			actions={ [
				{
					label: buttonLabel,
					variant: 'secondary',
					onClick: () => {
						wcpayTracks.recordEvent(
							'wcpay_order_dispute_notice_action_click',
							{
								due_by_days: countdownDays,
							}
						);
						window.location = disputeDetailsUrl;
					},
				},
			] }
		>
			{ <strong>{ `${ title } ${ suffix }` }</strong> }
		</InlineNotice>
	);
};

const DisputeUnderReviewNotice = () => {
	return (
		<InlineNotice status="warning" isDismissible={ false }>
			{ 'Under review innit' }
		</InlineNotice>
	);
};
