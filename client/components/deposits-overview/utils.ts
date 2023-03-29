/**
 * External dependencies
 */
import { sprintf } from '@wordpress/i18n';
import moment from 'moment';

/**
 * Internal dependencies
 */
import { formatCurrency } from 'utils/currency';
import strings from './strings';
import { getDepositMonthlyAnchorLabel } from 'wcpay/deposits/utils';

type NextDepositTableData = {
	date: number;
	status: string;
	amount: string;
};

/**
 * Formats the next deposit data from the overview object into an object that can be used in the Next Deposits table.
 *
 * @param {AccountOverview.Overview} overview - The overview object containing information about the next scheduled deposit.
 * @return {NextDepositTableData} An object containing the formatted next deposit data, with the following properties:
 * - date: A Unix timestamp representing the date of the next scheduled deposit.
 * - status: A string representing the status of the next scheduled deposit. If no status is provided, defaults to 'estimated.
 * - amount: A formatted string representing the amount of the next scheduled deposit in the currency specified in the overview object.
 */
export const getNextDeposit = (
	overview: AccountOverview.Overview
): NextDepositTableData => {
	if ( ! overview ) {
		return {
			date: 0,
			status: 'estimated',
			amount: formatCurrency( 0, 'USD' ),
		};
	}

	const { currency, nextScheduled } = overview;

	return {
		date: nextScheduled.date ?? 0,
		status: nextScheduled.status ?? 'estimated',
		amount: formatCurrency( nextScheduled.amount ?? 0, currency ),
	};
};

/**
 * Fetches the description for the deposit schedule from the account object.
 *
 * @param {AccountOverview.Account} account Next Deposit details props.
 * @return {string} Rendered element with section heading.
 */
export const getDepositScheduleDescription = (
	account: AccountOverview.Account
): string => {
	const schedule = account.deposits_schedule;
	let description = '';

	switch ( schedule.interval ) {
		case 'daily':
			description = strings.depositHistory.descriptions.daily;
			break;
		case 'weekly':
			const dayOfWeek = moment()
				.locale( 'en' )
				.day( schedule.weekly_anchor )
				.locale( moment.locale() )
				.format( 'dddd' );

			description = sprintf(
				strings.depositHistory.descriptions[ schedule.interval ],
				dayOfWeek
			);
			break;
		case 'monthly':
			const monthlyAnchor = schedule.monthly_anchor;

			// If the monthly anchor is 31, it means the deposit is scheduled for the last day of the month and has special handling.
			if ( monthlyAnchor === 31 ) {
				description =
					strings.depositHistory.descriptions.lastDayOfMonth;
				break;
			}

			description = sprintf(
				strings.depositHistory.descriptions[ schedule.interval ],
				getDepositMonthlyAnchorLabel( {
					monthlyAnchor: schedule.monthly_anchor,
					capitalize: false,
				} )
			);
			break;
		default:
			// This should never happen, but if it does, return an empty string.
			return '';
	}

	return description;
};
