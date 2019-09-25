/** @format */

/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { includes } from 'lodash';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../../constants';
import { resourcePrefixes } from './constants';
import {
	isResourcePrefix,
	getResourceIdentifier,
	getResourceName,
} from '../../utils';

/**
 * Returns a list of promises that will resolve to the requested resources.
 *
 * @param {Array} resourceNames List of requested resources.
 * @param {Function} fetch Function used to fetch data.
 * @returns {Array} Promises that will resolve to the requested resources.
 */
function read( resourceNames, fetch = apiFetch ) {
	return [
		...readTransactionsPage( resourceNames, fetch, transactionsPageToResources ),
		...readTransactionsSummary( resourceNames, fetch, transactionsSummaryToResources ),
	];
}

/**
 * Provided the right resource names will send requests for a specific transactions pages
 * and convert the data to resources.
 *
 * @param {Array} resourceNames List of requested resources.
 * @param {Function} fetch Function used to fetch data.
 * @param {Function} dataToResources Function used to convert requested data to resources.
 * @returns {Array} Promises that will resolve to the requested transactions pages.
 */
export function readTransactionsPage(
	resourceNames,
	fetch,
	dataToResources = transactionsPageToResources,
) {
	const resources = resourceNames.filter( resourceName => {
		// Only process requests for transactions page resources.
		return isResourcePrefix( resourceName, resourcePrefixes.list );
	} ).map( resourceName => {
		const data = getResourceIdentifier( resourceName );
		// TODO: I feel like this might not be the best way to send parameters with the request.
		//       Is there a better way to send the data with the request?
		const url = `${ NAMESPACE }/payments/transactions?page=${ data.page }&per_page=${ data.per_page }`;

		return fetch( { path: url } )
			.then( dataToResources )
			.catch( error => {
				return {
					[ resourceName ]: {
						error,
					},
				};
			} );
	} );

	return resources;
}

/**
 * Provided the right resource name will send a request for a summary
 * of all transactions and convert the data to resources.
 *
 * @param {Array} resourceNames List of requested resources.
 * @param {Function} fetch Function used to fetch data.
 * @param {Function} dataToResources Function used to convert requested data to resources.
 * @returns {Array} Promises that will resolve to the requested transactions summary.
 */
export function readTransactionsSummary(
	resourceNames,
	fetch,
	dataToResources = transactionsSummaryToResources,
) {
	if ( ! includes( resourceNames, resourcePrefixes.summary ) ) {
		return [];
	}

	const url = `${ NAMESPACE }/payments/transactions/summary`;

	return [
		fetch( { path: url } )
			.then( dataToResources )
			.catch( error => {
				return { [ resourcePrefixes.summary ]: { error } };
			} ),
	];
}

/**
 * Converts the raw transactions data from the API to resources.
 *
 * @param {Array} transactions Transactions returned from API.
 * @returns {Object} The resources created using the data provided.
 */
export function transactionsPageToResources( transactions ) {
	// TODO: Make sure page and per_page are returned with the transactions.
	// TODO: Maybe there's a better way to do this?
	// - Kristófer R. // @reykjalin.
	const identifier = {
		page: transactions.summary.page,
		per_page: transactions.summary.per_page,
	};
	const resourceName = getResourceName( resourcePrefixes.list, identifier );

	return {
		[ resourceName ]: {
			data: transactions,
		},
	};
}

/**
 * Converts the raw summary data from the API to resources.
 *
 * @param {Array} summary Summary returned from API.
 * @returns {Object} The resources created using the data provided.
 */
export function transactionsSummaryToResources( summary ) {
	return {
		[ resourcePrefixes.summary ]: {
			data: summary,
		},
	};
}

export default {
	read,
};
