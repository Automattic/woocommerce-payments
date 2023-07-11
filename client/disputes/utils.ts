/**
 * External dependencies
 */

import moment from 'moment';

/**
 * Internal dependencies
 */
import { CachedDispute, EvidenceDetails } from 'wcpay/types/disputes';

/**
 * Returns if a dispute is urgent or not.
 *
 * @param {string} dueBy The due by date of the dispute. See CachedDispute['due_by'].
 *
 * @return {boolean} True if the dispute is urgent, false otherwise.
 */
export const isDisputeUrgent = (
	dueBy: CachedDispute[ 'due_by' ] | EvidenceDetails[ 'due_by' ]
): boolean => {
	try {
		// Parse the due by date. If it's a number, it's a unix timestamp.
		const dueByMoment =
			typeof dueBy === 'number'
				? moment.unix( dueBy as number )
				: moment( dueBy as string );
		const now = moment().utc();
		const isUrgent = moment.utc( dueByMoment ).diff( now, 'hours' ) <= 72;
		return isUrgent;
	} catch ( e ) {
		// If we can't parse the date, we assume it's not urgent.
		return false;
	}
};
