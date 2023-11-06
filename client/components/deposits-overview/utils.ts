/**
 * Internal dependencies
 */
import { formatCurrency } from 'utils/currency';
import type { DepositStatus } from 'wcpay/types/deposits';
import type * as AccountOverview from 'wcpay/types/account-overview';

type NextDepositTableData = {
	id?: string;
	date: number;
	status: DepositStatus;
	amount: string;
};

/**
 * Formats the next deposit data from the overview object into an object that can be used in the Next Deposits table.
 *
 * @param {AccountOverview.Overview} [overview] - The overview object containing information about the next scheduled deposit.
 * @return {NextDepositTableData} An object containing the formatted next deposit data, with the following properties:
 * - id: An optional string representing the ID of the next scheduled deposit.
 * - date: A Unix timestamp representing the date of the next scheduled deposit.
 * - status: A string representing the status of the next scheduled deposit. If no status is provided, defaults to 'pending'.
 * - amount: A formatted string representing the amount of the next scheduled deposit in the currency specified in the overview object.
 */
export const getNextDeposit = (
	overview?: AccountOverview.Overview
): NextDepositTableData => {
	if ( ! overview?.nextScheduled ) {
		return {
			id: undefined,
			date: 0,
			status: 'pending',
			amount: formatCurrency( 0, overview?.currency ),
		};
	}

	const { currency, nextScheduled } = overview;

	return {
		id: nextScheduled.id,
		date: nextScheduled.date ?? 0,
		status: nextScheduled.status ?? '-',
		amount: formatCurrency( nextScheduled.amount ?? 0, currency ),
	};
};
