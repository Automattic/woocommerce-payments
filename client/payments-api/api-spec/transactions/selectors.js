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

const transactionsInitStatus = ( getResource ) => () => {
	const resourceName = 'transactions-list';
	const transactionsResource = getResource( resourceName );

	return ! ( isNil( transactionsResource.lastRequested ) || isNil( transactionsResource.lastReceived ) );
}

const getTransactionsIsLoading = ( getResource ) => () => {
	const resourceName = 'transactions-list';
	const transactionsResource = getResource( resourceName );

	return transactionsResource.lastRequested > transactionsResource.lastReceived;
}

const showTransactionsPlaceholder = ( getResource ) => () => {
	const isInitialized = transactionsInitStatus( getResource )();

	return ! isInitialized;
}

export default {
	getTransactions,
	getTransactionsIsLoading,
	transactionsInitStatus,
	showTransactionsPlaceholder,
};
