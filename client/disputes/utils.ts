/**
 * External dependencies
 */

import moment from 'moment';

/**
 * Internal dependencies
 */
import { CachedDispute, EvidenceDetails } from 'wcpay/types/disputes';

interface IsDueWithinProps {
	dueBy: CachedDispute[ 'due_by' ] | EvidenceDetails[ 'due_by' ] | undefined;
	days: number;
}
/**
 * Returns true if a dispute due_by date is within the specified number of days.
 * Returns false if the dispute due_by date is not within the specified number of days
 * or if the due_by value is not a valid date.
 *
 * @param {IsDueWithinProps} dispute - An object containing the dispute due_by date, either in unix timestamp (number) or ISO format (string).
 * @param {number} days - The number of days to check.
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
	const isWithinDays = dueByMoment.diff( now, 'days', true ) < days;
	const isPastDue = now.isAfter( dueByMoment );
	return isWithinDays && ! isPastDue;
};
