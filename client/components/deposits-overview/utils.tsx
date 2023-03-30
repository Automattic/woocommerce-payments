/**
 * External dependencies
 */
import React from 'react';
import { sprintf } from '@wordpress/i18n';
import moment from 'moment';
import interpolateComponents from '@automattic/interpolate-components';

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
 * @param {AccountOverview.Account} account The account object containing information about the deposit schedule.
 * @return {JSX.Element} The description for the deposit schedule.
 */
export const getDepositScheduleDescription = (
	account: AccountOverview.Account
): JSX.Element => {
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
	}

	return interpolateComponents( {
		mixedString: description,
		components: {
			strong: <strong />,
		},
	} );
};

/**
 * Checks if the account deposits are blocked.
 *
 * @param {AccountOverview.Account} account The account object containing information about the account.
 * @return {boolean} Whether the account deposits are blocked.
 */
export const areDepositsBlocked = (
	account: AccountOverview.Account
): boolean => {
	const schedule = account.deposits_schedule;

	/*
	 * Check if the account is blocked.
	 *
	 * Accounts that have a manual interval prior to the custom deposit schedule
	 * feature, are considered suspended. This will change once manual deposits are supported.
	 */
	const isCustomDepositSchedulesEnabled =
		wcpaySettings?.featureFlags?.customDepositSchedules;

	return (
		account.deposits_blocked ||
		( ! isCustomDepositSchedulesEnabled && 'manual' === schedule.interval )
	);
};
