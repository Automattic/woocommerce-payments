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

const isTransactionWaitingForInitialLoad = ( getResource ) => ( transactionId ) => {
	return ! getResource( transactionId ).lastReceived;
};

const isTransactionLoading = ( getResource ) => ( transactionId ) => {
	const transaction = getResource( transactionId );
	return ( ! transaction.lastReceived ) || ( transaction.lastRequested > transaction.lastReceived );
};

const getTransactions = ( getResource, requireResource ) => (
	requirement = DEFAULT_REQUIREMENT
) => {
	return requireResource( requirement, 'transactions-list' ).data || {};
};

const isTransactionListWaitingForInitialLoad = ( getResource ) => () => {
	const resourceName = 'transactions-list';
	const transactionsResource = getResource( resourceName );

	return transactionsResource.lastReceived === undefined;
};

const isTransactionListLoading = ( getResource ) => () => {
	const resourceName = 'transactions-list';
	const transactionsResource = getResource( resourceName );

	return transactionsResource.lastRequested > transactionsResource.lastReceived;
};

const showTransactionsPlaceholder = ( getResource ) => () => {
	return isTransactionListWaitingForInitialLoad( getResource )();
};

export default {
	getTransaction,
	isTransactionWaitingForInitialLoad,
	isTransactionLoading,
	getTransactions,
	isTransactionListLoading,
	isTransactionListWaitingForInitialLoad,
	showTransactionsPlaceholder,
};
