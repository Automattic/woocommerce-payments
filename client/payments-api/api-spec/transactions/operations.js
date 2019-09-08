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
import {
	isResourcePrefix,
	getResourceIdentifier,
	getResourceName,
} from '../../utils';
import { resourcePrefixes } from './constants';

function read( resourceNames, fetch = apiFetch ) {
	return [
		...readTransactionsPage( resourceNames, fetch, transactionsPageToResources ),
		...readTransactionsSummary( resourceNames, fetch, transactionsSummaryToResources ),
	];
}

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

export function transactionsSummaryToResources( numberOfTransactions ) {
	return {
		[ resourcePrefixes.summary ]: {
			data: numberOfTransactions,
		},
	};
}

export default {
	read,
};
