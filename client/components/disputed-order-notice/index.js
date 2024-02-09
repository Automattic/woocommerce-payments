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
	isRefundable,
	isUnderReview,
} from 'wcpay/disputes/utils';
import { useCharge } from 'wcpay/data';
import { recordEvent } from 'tracks';
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
		if ( ! isRefundable( dispute.status ) ) {
			onDisableOrderRefund( dispute.status );
		}
	}, [ charge, onDisableOrderRefund ] );

	const { dispute } = charge;
	if ( ! charge?.dispute ) {
		return null;
	}

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
	if ( dispute.status === 'lost' ) {
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

const UrgentDisputeNoticeBody = ( {
	isPreDisputeInquiry,
	disputeReason,
	formattedAmount,
	dueBy,
	countdownDays,
} ) => {
	const formatString = isPreDisputeInquiry
		? __(
				// Translators: %1$s is the formatted dispute amount, %2$s is the dispute reason, %3$s is the due date.
				"Please resolve the inquiry on this order of %1$s labeled '%2$s' by %3$s.",
				'woocommerce-payments'
		  )
		: __(
				// Translators: %1$s is the formatted dispute amount, %2$s is the dispute reason, %3$s is the due date.
				"Please resolve the dispute on this order of %1$s labeled '%2$s' by %3$s.",
				'woocommerce-payments'
		  );

	const message = sprintf(
		formatString,
		formattedAmount,
		reasons[ disputeReason ].display,
		dateI18n( 'M j, Y', dueBy.local().toISOString() )
	);

	let suffix = sprintf(
		// Translators: %s is the number of days left to respond to the dispute.
		_n(
			'(%s day left)',
			'(%s days left)',
			countdownDays,
			'woocommerce-payments'
		),
		countdownDays
	);
	if ( countdownDays < 1 ) {
		suffix = __( '(Last day today)', 'woocommerce-payments' );
	}

	return (
		<>
			<strong>{ message }</strong> { suffix }
		</>
	);
};

const RegularDisputeNoticeBody = ( {
	isPreDisputeInquiry,
	disputeReason,
	formattedAmount,
	dueBy,
} ) => {
	const formatString = isPreDisputeInquiry
		? __(
				// Translators: %1$s is the formatted dispute amount, %2$s is the dispute reason.
				"Please resolve the inquiry on this order of %1$s with reason '%2$s'.",
				'woocommerce-payments'
		  )
		: __(
				// Translators: %1$s is the formatted dispute amount, %2$s is the dispute reason.
				"This order has a payment dispute for %1$s for the reason '%2$s'. ",
				'woocommerce-payments'
		  );

	const boldMessage = sprintf(
		formatString,
		formattedAmount,
		reasons[ disputeReason ].display
	);

	const suffix = sprintf(
		// Translators: %1$s is the dispute due date.
		__( 'Please respond before %1$s.', 'woocommerce-payments' ),
		dateI18n( 'M j, Y', dueBy.local().toISOString() )
	);

	return (
		<>
			<strong>{ boldMessage }</strong> { suffix }
		</>
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
		recordEvent( 'wcpay_order_dispute_notice_view', {
			is_inquiry: isPreDisputeInquiry,
			dispute_reason: disputeReason,
			due_by_days: countdownDays,
		} );
	}, [ isPreDisputeInquiry, disputeReason, countdownDays ] );

	const isUrgent = countdownDays < 7;

	const buttonLabel =
		countdownDays < 1
			? __( 'Respond today', 'woocommerce-payments' )
			: __( 'Respond now', 'woocommerce-payments' );

	const noticeBody = isUrgent ? (
		<UrgentDisputeNoticeBody
			isPreDisputeInquiry={ isPreDisputeInquiry }
			disputeReason={ disputeReason }
			formattedAmount={ formattedAmount }
			dueBy={ dueBy }
			countdownDays={ countdownDays }
		/>
	) : (
		<RegularDisputeNoticeBody
			isPreDisputeInquiry={ isPreDisputeInquiry }
			disputeReason={ disputeReason }
			formattedAmount={ formattedAmount }
			dueBy={ dueBy }
		/>
	);

	return (
		<InlineNotice
			status={ countdownDays < 3 ? 'error' : 'warning' }
			isDismissible={ false }
			actions={ [
				{
					label: buttonLabel,
					variant: 'secondary',
					onClick: () => {
						recordEvent(
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
			{ noticeBody }
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
