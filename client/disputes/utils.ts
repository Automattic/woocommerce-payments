/**
 * External dependencies
 */

import moment from 'moment';

/**
 * Internal dependencies
 */
import { CachedDispute, EvidenceDetails } from 'wcpay/types/disputes';

/**
 * Returns true if a dispute due_by date is within the specified number of days.
 * Returns false if the dispute due_by date is not within the specified number of days
 * or if the due_by value is not a valid date.
 *
 * @param {Object} dispute - The dispute to check. See {@link CachedDispute} or {@link EvidenceDetails}.
 * @param {number} days - The number of days to check.
 *
 * @return {boolean} True if the dispute is due within the specified number of days.
 */
interface IsDueWithinProps {
	dueBy: CachedDispute[ 'due_by' ] | EvidenceDetails[ 'due_by' ];
	days: number;
}
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
	const isWithinDays = dueByMoment.diff( now, 'days', true ) < days;
	const isPastDue = now.isAfter( dueByMoment );
	return isWithinDays && ! isPastDue;
};

interface isDisputeAwaitingResponseProps {
	status: CachedDispute[ 'status' ];
}
/**
 * Checks a dispute status and returns true if a dispute is awaiting a response from the merchant.
 *
 * @param {isDisputeAwaitingResponseProps} props - An object containing the dispute status.
 * @return {boolean} True if the dispute status matches one of the awaiting response statuses.
 */
export const isDisputeAwaitingResponse = ( {
	status,
}: isDisputeAwaitingResponseProps ): boolean =>
	[ 'needs_response', 'warning_needs_response' ].includes( status );
