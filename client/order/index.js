/* global jQuery */

import { __, _n, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import ReactDOM from 'react-dom';
import { dispatch } from '@wordpress/data';
import moment from 'moment';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { getConfig } from 'utils/order';
import RefundConfirmationModal from './refund-confirm-modal';
import CancelConfirmationModal from './cancel-confirm-modal';
import BannerNotice from 'wcpay/components/banner-notice';
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
		const originalStatus = $( 'input#original_post_status' ).val();
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

		ReactDOM.render( <DisputeNotice chargeId={ chargeId } />, container );
	}
} );

const DisputeNotice = ( { chargeId } ) => {
	const { data: charge } = useCharge( chargeId );

	if ( ! charge?.dispute ) {
		return null;
	}

	const { dispute } = charge;

	let urgency = 'warning';
	let actions;

	// Refunds are only allowed if the dispute is an inquiry or if it's won.
	const isRefundable =
		isInquiry( dispute ) || [ 'won' ].includes( dispute.status );
	const shouldDisableRefund = ! isRefundable;
	let disableRefund = false;

	let refundDisabledNotice = '';
	if ( shouldDisableRefund ) {
		const refundButton = document.querySelector( 'button.refund-items' );
		if ( refundButton ) {
			disableRefund = true;

			// Disable the refund button.
			refundButton.disabled = true;

			const disputeDetailsLink = getDetailsURL( dispute.id, 'disputes' );
			// TODO: finalize text copy after we come up with the final decision.
			refundDisabledNotice = createInterpolateElement(
				__(
					'Refunds and order editing are disabled because this order was disputed. <a>View dispute</a>',
					'woocommerce-payments'
				),
				{
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					a: <a href={ disputeDetailsLink } />,
				}
			);
			let tooltipText = __(
				'Refunds and order editing are disabled because this order was disputed.',
				'woocommerce-payments'
			);

			if ( isAwaitingResponse( dispute.status ) ) {
				refundDisabledNotice = __(
					'Refunds and order editing are disabled during disputes.',
					'woocommerce-payments'
				);
				tooltipText = refundDisabledNotice;
			} else if ( isUnderReview( dispute.status ) ) {
				refundDisabledNotice = createInterpolateElement(
					__(
						'This order has a payment dispute. Refunds and order editing are disabled during disputes. <a>View dispute</a>',
						'woocommerce-payments'
					),
					{
						// eslint-disable-next-line jsx-a11y/anchor-has-content
						a: <a href={ disputeDetailsLink } />,
					}
				);
				tooltipText = __(
					'Refunds and order editing are disabled during disputes.',
					'woocommerce-payments'
				);
			}

			// Change refund tooltip's text copy.
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
	}

	let showWarning = false;
	let warningText = '';

	if (
		dispute.evidence_details?.due_by &&
		// Only show the notice if the dispute is awaiting a response.
		isAwaitingResponse( dispute.status )
	) {
		const now = moment();
		const dueBy = moment.unix( dispute.evidence_details?.due_by );
		const countdownDays = Math.floor( dueBy.diff( now, 'days', true ) );

		// If the dispute is due in the past, we don't want to show the notice.
		if ( ! now.isAfter( dueBy ) ) {
			showWarning = true;

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
			const amountFormatted = formatExplicitCurrency(
				dispute.amount,
				dispute.currency
			);

			let buttonLabel = __( 'Respond now', 'woocommerce-payments' );
			let suffix = '';

			let titleText = isInquiry( dispute )
				? titleStrings.inquiry_default
				: titleStrings.dispute_default;

			// If the dispute is due within 7 days, use different wording.
			if ( countdownDays < 7 ) {
				titleText = isInquiry( dispute )
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
				amountFormatted,
				reasons[ dispute.reason ].display,
				dateI18n( 'M j, Y', dueBy.local().toISOString() )
			);

			// If the dispute is due within 72 hours, we want to highlight it as urgent/red.
			if ( countdownDays < 3 ) {
				urgency = 'error';
			}

			if ( countdownDays < 1 ) {
				buttonLabel = __( 'Respond today', 'woocommerce-payments' );
				suffix = __( '(Last day today)', 'woocommerce-payments' );
			}

			actions = [
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
						window.location = getDetailsURL(
							dispute.id,
							'disputes'
						);
					},
				},
			];

			warningText = `${ title } ${ suffix }`;
		}
	}

	if ( ! showWarning && ! disableRefund ) {
		return null;
	}

	return (
		<BannerNotice
			status={ urgency }
			isDismissible={ false }
			actions={ actions }
		>
			{ showWarning && <strong>{ warningText }</strong> }

			{ disableRefund && <div>{ refundDisabledNotice }</div> }
		</BannerNotice>
	);
};
