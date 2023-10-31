import moment from 'moment';
import React, { useEffect } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import InlineNotice from 'wcpay/components/inline-notice';
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

const DisputedOrderNoticeHandler = ( { chargeId, onDisableOrderRefund } ) => {
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
			onDisableOrderRefund( dispute.status );
		}
	}, [ charge, onDisableOrderRefund ] );

	const { dispute } = charge;
	if ( ! charge?.dispute ) {
		return null;
	}
	const isRefundable =
		isInquiry( dispute ) || [ 'won' ].includes( dispute.status );

	// Special case the dispute "under review" notice which is much simpler.
	// (And return early.)
	if ( isUnderReview( dispute.status ) && ! isInquiry( dispute ) ) {
		return (
			<DisputeOrderLockedNotice
				message={ __(
					'This order has an active payment dispute. Refunds and order editing are disabled.',
					'woocommerce-payments'
				) }
				disputeDetailsUrl={ disputeDetailsUrl }
			/>
		);
	}

	// Special case lost disputes.
	// (And return early.)
	// I suspect this is unnecessary, as any lost disputes will have already been
	// refunded as part of `charge.dispute.closed` webhook handler.
	// This may be dead code. Leaving in for now as this is consistent with
	// the logic before this PR.
	// https://github.com/Automattic/woocommerce-payments/pull/7557
	if ( dispute.status === 'lost' && ! isRefundable ) {
		return (
			<DisputeOrderLockedNotice
				message={ __(
					'Refunds and order editing have been disabled as a result of a lost dispute.',
					'woocommerce-payments'
				) }
				disputeDetailsUrl={ disputeDetailsUrl }
			/>
		);
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

const DisputeOrderLockedNotice = ( { message, disputeDetailsUrl } ) => {
	return (
		<InlineNotice status="warning" isDismissible={ false }>
			{ message }
			{ createInterpolateElement(
				__( ' <a>View details</a>', 'woocommerce-payments' ),
				{
					// createInterpolateElement is incompatible with this eslint rule as the <a> is decoupled from content.
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					a: <a href={ disputeDetailsUrl } />,
				}
			) }
		</InlineNotice>
	);
};

export default DisputedOrderNoticeHandler;
