
/**
 * Internal dependencies.
 */
import { DEFAULT_REQUIREMENT } from '../../constants';

const getTransactions = ( getResource, requireResource ) => (
	requirement = DEFAULT_REQUIREMENT
) => {
	const resourceName = 'transactions-list';
	return requireResource( requirement, resourceName ).data || {};
}

const getTransactionsIsLoading = ( getResource, requireResource ) => (
	requirement = DEFAULT_REQUIREMENT
) => {
	const resourceName = 'transactions-list';
	const transactions = requireResource( requirement, resourceName ).data || {};

	// If no transactions are available, assume the request is loading.
	if ( ! transactions.data ) {
		return true;
	}

	return transactions.lastRequested > transactions.lastReceived;
}

export default {
	getTransactions,
	getTransactionsIsLoading,
};
