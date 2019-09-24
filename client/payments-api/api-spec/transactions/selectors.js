/** @format */

/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import { DEFAULT_REQUIREMENT } from '../../constants';
import { getResourceName } from '../../utils';
import { resourcePrefixes } from './constants';

const getTransactionsPage = ( getResource, requireResource ) => (
	page,
	rowsPerPage,
	requirement = DEFAULT_REQUIREMENT
) => {
	const identifier = { page: parseInt( page ), per_page: parseInt( rowsPerPage ) };
	const resourceName = getResourceName( resourcePrefixes.list, identifier );

	const res = requireResource( requirement, resourceName ).data || {};
	return res;
};

const getTransactionsSummary = ( getResource, requireResource ) => (
	requirement = DEFAULT_REQUIREMENT,
) => {
	return requireResource( requirement, resourcePrefixes.summary ).data || {};
};

const isWaitingForInitialPageLoad = ( getResource ) => (
	page,
	rowsPerPage,
) => {
	const identifier = { page: page, per_page: rowsPerPage };
	const resourceName = getResourceName( resourcePrefixes.list, identifier );
	const transactionsResource = getResource( resourceName );

	return transactionsResource.data === undefined;
};

const getTransactionsPageIsLoading = ( getResource ) => (
	page,
	rowsPerPage,
) => {
	const identifier = {
		page: page,
		per_page: rowsPerPage,
	};
	const resourceName = getResourceName( resourcePrefixes.list, identifier );
	const transactionsResource = getResource( resourceName );

	return transactionsResource.lastRequested > transactionsResource.lastReceived;
};

const showTransactionsPagePlaceholder = ( getResource ) => (
	page,
	rowsPerPage,
) => {
	return isWaitingForInitialPageLoad( getResource )( parseInt( page ), parseInt( rowsPerPage ) );
};

export default {
	getTransactionsPage,
	getTransactionsPageIsLoading,
	getTransactionsSummary,
	isWaitingForInitialPageLoad,
	showTransactionsPagePlaceholder,
};
