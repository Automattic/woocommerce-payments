/**
 * Internal dependencies
 */
import { formatCurrency } from 'utils/currency';

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
