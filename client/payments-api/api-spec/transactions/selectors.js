/** @format */

/**
 * External dependencies
 */
import { slice } from 'lodash';

/**
 * Internal dependencies
 */
import { DEFAULT_REQUIREMENT } from '../../constants';

const getTransactions = ( getResource, requireResource ) => (
	requirement = DEFAULT_REQUIREMENT
) => {
	return requireResource( requirement, 'transactions-list' ).data || {};
};

const getTransactionsPage = ( getResource, requireResource ) => (
	page,
	rowsPerPage,
	requirement = DEFAULT_REQUIREMENT
) => {
	const res = requireResource( requirement, 'transactions-list' ).data || {};
	if ( res && res.data ) {
		const data = [
			...res.data,
			...res.data,
			...res.data,
			...res.data,
			...res.data,
			...res.data,
			...res.data,
			...res.data,
			...res.data,
			...res.data,
		];

		return {
			...res,
			data: slice( data, rowsPerPage * ( page - 1 ), rowsPerPage * page ),
		};
	}
	return res;
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
	getTransactions,
	getTransactionsIsLoading,
	isWaitingForInitialLoad,
	showTransactionsPlaceholder,
	getTransactionsPage,
};
