/**
 * External dependencies
 */

import moment from 'moment';

/**
 * Internal dependencies
 */
import type {
	CachedDispute,
	Dispute,
	DisputeStatus,
	EvidenceDetails,
} from 'wcpay/types/disputes';
import type { BalanceTransaction } from 'wcpay/types/balance-transactions';
import {
	disputeAwaitingResponseStatuses,
	disputeUnderReviewStatuses,
} from 'wcpay/disputes/filters/config';
import { formatCurrency, formatExplicitCurrency } from 'wcpay/utils/currency';

interface IsDueWithinProps {
	dueBy: CachedDispute[ 'due_by' ] | EvidenceDetails[ 'due_by' ];
	days: number;
}
/**
 * Returns true if a dispute due_by date is within the specified number of days.
 * Returns false if the dispute due_by date is not within the specified number of days
 * or if the due_by value is not a valid date.
 *
 * @param {IsDueWithinProps} props - An object containing function arguments.
 * @param {number} props.dueBy - The dispute due_by date. Accepts a unix timestamp {@link EvidenceDetails} or a date string {@link CachedDispute}.
 * @param {number} props.days - The number of days to check.
 *
 * @return {boolean} True if the dispute is due within the specified number of days.
 */
export const isDueWithin = ( { dueBy, days }: IsDueWithinProps ): boolean => {
	if ( ! dueBy ) {
		return false;
	}

	// Parse the due by date. If it's a number, it's a unix timestamp.
	const dueByMoment =
		typeof dueBy === 'number'
			? moment.unix( dueBy as number )
			: moment.utc( dueBy as string, true );

	if ( ! dueByMoment.isValid() ) {
		// If we can't parse the date, we assume it's not urgent.
		return false;
	}

	const now = moment().utc();
	const isWithinDays = dueByMoment.diff( now, 'days', true ) <= days;
	const isPastDue = now.isAfter( dueByMoment );
	return isWithinDays && ! isPastDue;
};

export const isAwaitingResponse = (
	status: DisputeStatus | string
): boolean => {
	return disputeAwaitingResponseStatuses.includes( status );
};

export const isUnderReview = ( status: DisputeStatus | string ): boolean => {
	return disputeUnderReviewStatuses.includes( status );
};

export const isInquiry = ( dispute: Pick< Dispute, 'status' > ): boolean => {
	// Inquiry dispute statuses are one of `warning_needs_response`, `warning_under_review` or `warning_closed`.
	return dispute.status.startsWith( 'warning' );
};

export const isRefundable = ( status: DisputeStatus ): boolean => {
	// Refundable dispute statuses are one of `warning_needs_response`, `warning_under_review`, `warning_closed` or `won`.
	return isInquiry( { status } ) || 'won' === status;
};

/**
 * Returns the dispute fee balance transaction for a dispute if it exists
 * and the deduction has not been reversed.
 */
const getDisputeDeductedBalanceTransaction = (
	dispute: Pick< Dispute, 'balance_transactions' >
): BalanceTransaction | undefined => {
	// Note that there can only be, at most, two balance transactions for a given dispute:

	// One balance transaction with reporting_category: 'dispute' will be present if funds have been withdrawn from the account.
	const disputeFee = dispute.balance_transactions.find(
		( transaction ) => transaction.reporting_category === 'dispute'
	);

	// A second balance transaction with the reporting_category: 'dispute_reversal' will be present if funds have been reinstated to the account.
	const disputeFeeReversal = dispute.balance_transactions.find(
		( transaction ) => transaction.reporting_category === 'dispute_reversal'
	);

	if ( disputeFeeReversal ) {
		return undefined;
	}

	return disputeFee;
};

/**
 * Returns the dispute fee formatted as a currency string if it exists
 * and the deduction has not been reversed.
 */
export const getDisputeFeeFormatted = (
	dispute: Pick< Dispute, 'balance_transactions' >,
	appendCurrencyCode?: boolean
): string | undefined => {
	const disputeFee = getDisputeDeductedBalanceTransaction( dispute );

	if ( ! disputeFee ) {
		return undefined;
	}

	if ( appendCurrencyCode ) {
		return formatExplicitCurrency( disputeFee.fee, disputeFee.currency );
	}

	return formatCurrency( disputeFee.fee, disputeFee.currency );
};
