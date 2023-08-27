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
import { disputeUnderReviewStatuses } from 'wcpay/disputes/filters/config';

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

export const isUnderReview = ( status: DisputeStatus | string ): boolean => {
	return disputeUnderReviewStatuses.includes( status );
};

export const isInquiry = ( dispute: Dispute | CachedDispute ): boolean => {
	// Inquiry dispute statuses are one of `warning_needs_response`, `warning_under_review` or `warning_closed`.
	return dispute.status.startsWith( 'warning' );
};
