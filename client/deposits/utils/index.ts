/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';

/**
 * Internal dependencies
 */
import type * as AccountOverview from 'wcpay/types/account-overview';

const formatDate = ( format: string, date: number | string ) =>
	dateI18n(
		format,
		moment.utc( date ).toISOString(),
		true // TODO Change call to gmdateI18n and remove this deprecated param once WP 5.4 support ends.
	);

interface DepositObject {
	date: number | string;
}
export const getDepositDate = ( deposit?: DepositObject | null ): string =>
	deposit ? formatDate( 'F j, Y', deposit?.date ) : '—';

interface GetDepositMonthlyAnchorLabelProps {
	monthlyAnchor: number;
	capitalize?: boolean;
}
export const getDepositMonthlyAnchorLabel = ( {
	monthlyAnchor,
	capitalize = true,
}: GetDepositMonthlyAnchorLabelProps ): string => {
	// If locale is set up as en_US or en_GB the ordinal will not show up
	// More details can be found in https://github.com/WordPress/gutenberg/issues/15221/
	// Using 'en' as the locale should be enough to workaround it
	// see also getNextDepositDate
	// TODO: This was resolved in WP 6.1. When the required version is 6.1 or higher, we can remove this workaround.
	const fixedLocale = moment.locale().startsWith( 'en' )
		? 'en'
		: moment.locale();

	let label = moment()
		.locale( fixedLocale )
		.date( monthlyAnchor )
		.format( 'Do' );

	if ( 31 === monthlyAnchor ) {
		label = __( 'Last day of the month', 'woocommerce-payments' );
	}
	if ( ! capitalize ) {
		label = label.toLowerCase();
	}
	return label;
};

export const getNextDepositDate = (
	depositSchedule: AccountOverview.Account[ 'deposits_schedule' ]
): string => {
	// See comment on getDepositMonthlyAnchorLabel for why we're using 'en' as the locale
	const fixedLocale = moment.locale().startsWith( 'en' )
		? 'en'
		: moment.locale();
	const dateFormat = 'MMMM Do, YYYY';

	// Create a base moment instance with the appropriate locale
	const baseMoment = moment().locale( fixedLocale );
	const today = baseMoment.clone();

	switch ( depositSchedule.interval ) {
		case 'daily':
			return today.add( 1, 'days' ).format( dateFormat );
		case 'weekly':
			const weeklyAnchor = baseMoment.day(
				depositSchedule.weekly_anchor
			);
			const nextWeeklyAnchor = weeklyAnchor.isAfter( today, 'day' )
				? weeklyAnchor
				: weeklyAnchor.add( 1, 'weeks' );
			return nextWeeklyAnchor.format( dateFormat );
		case 'monthly':
			const nextDate = baseMoment.clone();

			if ( today.date() >= depositSchedule.monthly_anchor ) {
				nextDate
					.date( 1 ) // set to the first of the month first, otherwise we could overshoot the next month
					.add( 1, 'months' );
			}

			// Ensure the date isn't invalid (e.g., Feb 30). If it's invalid, set to the last day of the next month.
			if ( nextDate.daysInMonth() < depositSchedule.monthly_anchor ) {
				nextDate.endOf( 'month' );
			} else {
				nextDate.date( depositSchedule.monthly_anchor );
			}
			return nextDate.format( dateFormat );
		default:
			return __( '—', 'woocommerce-payments' );
	}
};
