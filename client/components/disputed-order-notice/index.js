import moment from 'moment';
import React, { useEffect } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import InlineNotice from 'wcpay/components/inline-notice';
import { formatExplicitCurrency } from 'multi-currency/interface/functions';
import { reasons } from 'wcpay/disputes/strings';
import { getDetailsURL } from 'wcpay/components/details-link';
import {
	isAwaitingResponse,
	isInquiry,
	isRefundable,
	isUnderReview,
} from 'wcpay/disputes/utils';
import { getChargeDisputes } from 'wcpay/utils/charge';
import { useCharge } from 'wcpay/data/charges';
import { recordEvent } from 'tracks';
import './style.scss';
import { formatDateTimeFromString } from 'wcpay/utils/date-time';

const DisputedOrderNoticeHandler = ( { chargeId, onDisableOrderRefund } ) => {
	const { data: charge } = useCharge( chargeId );
	const disputeDetailsUrl = getDetailsURL( chargeId, 'transactions' );

	// A charge can carry more than one dispute (e.g. AmEx/Klarna partial
	// disputes). `getChargeDisputes` returns them all, falling back to the
	// single `charge.dispute` for payloads without the array.
	const disputes = charge ? getChargeDisputes( charge ) : [];

	// Disable the refund button if any dispute blocks refunds.
	useEffect( () => {
		const blocking = ( charge ? getChargeDisputes( charge ) : [] ).find(
			( dispute ) => ! isRefundable( dispute.status )
		);
		if ( blocking ) {
			onDisableOrderRefund( blocking.status );
		}
	}, [ charge, onDisableOrderRefund ] );

	if ( ! disputes.length ) {
		return null;
	}

	// Refund/edit locking and the "under review" / "lost" states are
	// charge-wide, so surface them if ANY dispute is in that state before
	// considering the respond-by notices below.

	// Special case the dispute "under review" notice which is much simpler.
	if (
		disputes.some(
			( dispute ) =>
				isUnderReview( dispute.status ) && ! isInquiry( dispute.status )
		)
	) {
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
	// I suspect this is unnecessary, as any lost disputes will have already been
	// refunded as part of `charge.dispute.closed` webhook handler.
	// This may be dead code. Leaving in for now as this is consistent with
	// the logic before this PR.
	// https://github.com/Automattic/woocommerce-payments/pull/7557
	if ( disputes.some( ( dispute ) => dispute.status === 'lost' ) ) {
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

	// Get current time in UTC for consistent timezone-independent comparison.
	const now = moment().utc();

	// Disputes still awaiting a response, with a deadline in the future.
	const awaitingDisputes = disputes.filter( ( dispute ) => {
		if (
			! isAwaitingResponse( dispute.status ) ||
			! dispute.evidence_details?.due_by
		) {
			return false;
		}
		// Parse the Unix timestamp as UTC since it's stored that way in the API.
		return now.isBefore(
			moment.unix( dispute.evidence_details.due_by ).utc()
		);
	} );

	if ( ! awaitingDisputes.length ) {
		return null;
	}

	// A single dispute keeps its detailed, reason-specific notice. Several are
	// consolidated into one so the order screen isn't stacked with
	// near-identical notices — the transaction page (one click away) breaks
	// them down per dispute.
	if ( awaitingDisputes.length === 1 ) {
		const dispute = awaitingDisputes[ 0 ];
		const dueBy = moment.unix( dispute.evidence_details.due_by ).utc();
		return (
			<DisputeNeedsResponseNotice
				chargeId={ chargeId }
				disputeReason={ dispute.reason }
				formattedAmount={ formatExplicitCurrency(
					dispute.amount,
					dispute.currency
				) }
				isPreDisputeInquiry={ isInquiry( dispute.status ) }
				dueBy={ dueBy }
				countdownDays={ Math.floor( dueBy.diff( now, 'days', true ) ) }
				disputeDetailsUrl={ disputeDetailsUrl }
			/>
		);
	}

	const earliestDueBy = awaitingDisputes
		.map( ( dispute ) =>
			moment.unix( dispute.evidence_details.due_by ).utc()
		)
		.reduce( ( earliest, dueBy ) =>
			dueBy.isBefore( earliest ) ? dueBy : earliest
		);
	// Disputes on one charge share its currency, so summing the minor-unit
	// amounts and formatting with the first dispute's currency is safe.
	const totalAmount = awaitingDisputes.reduce(
		( sum, dispute ) => sum + dispute.amount,
		0
	);

	return (
		<MultipleDisputesNeedsResponseNotice
			disputeCount={ awaitingDisputes.length }
			formattedAmount={ formatExplicitCurrency(
				totalAmount,
				awaitingDisputes[ 0 ].currency
			) }
			dueBy={ earliestDueBy }
			countdownDays={ Math.floor(
				earliestDueBy.diff( now, 'days', true )
			) }
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
		formatDateTimeFromString( dueBy.toISOString() )
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
		formatDateTimeFromString( dueBy.toISOString() )
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

// Consolidated notice for a charge with several disputes awaiting a response.
// Rather than stack a full per-dispute notice for each, it sums the disputed
// amounts and surfaces the earliest deadline; the transaction page it links to
// breaks the disputes down individually.
const MultipleDisputesNeedsResponseNotice = ( {
	disputeCount,
	formattedAmount,
	dueBy,
	countdownDays,
	disputeDetailsUrl,
} ) => {
	useEffect( () => {
		recordEvent( 'wcpay_order_dispute_notice_view', {
			is_inquiry: false,
			dispute_reason: 'multiple',
			due_by_days: countdownDays,
			dispute_count: disputeCount,
		} );
	}, [ countdownDays, disputeCount ] );

	const buttonLabel =
		countdownDays < 1
			? __( 'Respond today', 'woocommerce-payments' )
			: __( 'Respond now', 'woocommerce-payments' );

	const message = sprintf(
		// Translators: %1$d is the number of disputes on the order, %2$s is the combined disputed amount.
		__(
			'This order has %1$d payment disputes totaling %2$s.',
			'woocommerce-payments'
		),
		disputeCount,
		formattedAmount
	);

	let suffix = sprintf(
		// Translators: %1$s is the earliest dispute due date.
		__( 'Please respond before %1$s.', 'woocommerce-payments' ),
		formatDateTimeFromString( dueBy.toISOString() )
	);
	if ( countdownDays < 7 ) {
		const daysLeft =
			countdownDays < 1
				? __( '(Last day today)', 'woocommerce-payments' )
				: sprintf(
						// Translators: %s is the number of days left to respond to the earliest dispute.
						_n(
							'(%s day left)',
							'(%s days left)',
							countdownDays,
							'woocommerce-payments'
						),
						countdownDays
				  );
		suffix = `${ suffix } ${ daysLeft }`;
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
			<strong>{ message }</strong> { suffix }
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
