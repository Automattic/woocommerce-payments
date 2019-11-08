/** @format */

/**
 * Internal dependencies
 */
import { DEFAULT_REQUIREMENT } from '../../constants';

const getTransaction = ( getResource, requireResource ) => (
	transactionId,
	requirement = DEFAULT_REQUIREMENT
) => {
	return requireResource( requirement, transactionId ).data || {};
};

const getTransactionInitialLoad = ( getResource ) => ( transactionId ) => {
	return ! getResource( transactionId ).lastReceived;
};

const getTransactionIsLoading = ( getResource ) => ( transactionId ) => {
	const transaction = getResource( transactionId );
	return ( ! transaction.lastReceived ) || ( transaction.lastRequested > transaction.lastReceived );
};

const getTransactions = ( getResource, requireResource ) => (
	requirement = DEFAULT_REQUIREMENT
) => {
	return requireResource( requirement, 'transactions-list' ).data || {};
};

const isWaitingForInitialLoad = ( getResource ) => () => {
	const resourceName = 'transactions-list';
	const transactionsResource = getResource( resourceName );

	return transactionsResource.lastReceived === undefined;
};

const getTransactionsIsLoading = ( getResource ) => () => {
	const resourceName = 'transactions-list';
	const transactionsResource = getResource( resourceName );

	return transactionsResource.lastRequested > transactionsResource.lastReceived;
};

const showTransactionsPlaceholder = ( getResource ) => () => {
	return isWaitingForInitialLoad( getResource )();
};

export default {
	getTransaction,
	getTransactionInitialLoad,
	getTransactionIsLoading,
	getTransactions,
	getTransactionsIsLoading,
	isWaitingForInitialLoad,
	showTransactionsPlaceholder,
};
