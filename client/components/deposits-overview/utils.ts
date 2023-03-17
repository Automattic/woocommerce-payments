/**
 * Internal dependencies
 */
import { formatCurrency } from 'utils/currency';

/**
 * Formats the next deposit data from the overview object into an object to be used in the Next Deposits table.
 *
 * @param {AccountOverview.Overview} overview The overview object.
 * @return {Object} The next deposit data { date, status, amount}.
 */
export const getNextDeposit = (
	overview?: AccountOverview.Overview
): {
	date: number;
	status: string;
	amount: string;
} => {
	// Set default values for the next deposit.
	const nextDeposit: {
		date: number;
		status: string;
		amount: string;
	} = {
		date: 0,
		status: '',
		amount: '0',
	};

	if ( ! overview ) {
		return nextDeposit;
	}

	const { currency, nextScheduled } = overview;

	nextDeposit.date = nextScheduled.date;
	nextDeposit.status = nextScheduled.status ?? 'estimated';
	nextDeposit.amount = formatCurrency( nextScheduled.amount ?? 0, currency );

	return nextDeposit;
};
