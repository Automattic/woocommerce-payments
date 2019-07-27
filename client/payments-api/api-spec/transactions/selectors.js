/** @format */

/**
 * External dependencies.
 */
import { isNil } from 'lodash';

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

const isWaitingForInitialLoad = ( getResource ) => () => {
	const resourceName = 'transactions-list';
	const transactionsResource = getResource( resourceName );

	return transactionsResource.lastReceived === undefined;
}

const getTransactionsIsLoading = ( getResource ) => () => {
	const resourceName = 'transactions-list';
	const transactionsResource = getResource( resourceName );

	return transactionsResource.lastRequested > transactionsResource.lastReceived;
}

const showTransactionsPlaceholder = ( getResource ) => () => {
	return isWaitingForInitialLoad( getResource )();
}

export default {
	getTransactions,
	getTransactionsIsLoading,
	isWaitingForInitialLoad,
	showTransactionsPlaceholder,
};
