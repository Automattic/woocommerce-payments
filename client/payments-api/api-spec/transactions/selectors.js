
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

const getTransactionsIsLoading = ( getResource ) => {
	const resourceName = 'transactions-list';
	const transactionsResource = getResource( resourceName );
	const transactions = transactionsResource.data || {};

	// If no transactions are available, assume the request is loading.
	if ( ! transactions.data ) {
		return true;
	}

	return transactionsResource.lastRequested > transactionsResource.lastReceived;
}

export default {
	getTransactions,
	getTransactionsIsLoading,
};
