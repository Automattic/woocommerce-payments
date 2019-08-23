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
import { getTransactionsResourcePage, getTransactionsResourcePerPage } from './utils';

function read( resourceNames, fetch = apiFetch, dataToResources = transactionsToResources ) {
	return readTransactions( resourceNames, fetch, dataToResources );
}

export function readTransactions( resourceNames, fetch, dataToResources ) {
	if ( includes( resourceNames, 'transactions-list' ) ) {
		const url = `${ NAMESPACE }/payments/transactions`;

		return [
			fetch( { path: url } )
				.then( dataToResources )
				.catch( error => {
					return { [ 'transactions-list' ]: { error } };
				} ),
		];
	}

	return [];
}

export function readTransactionsPage( resourceNames, fetch = apiFetch, dataToResources = transactionsPageToResources ) {
	const resources = resourceNames.filter( resourceName => {
		// Only process requests for transactions page resources.
		const transactionsPagePattern = /^transactions-list-page-\d+-perpage-\d+$/;
		return null !== resourceName.match( transactionsPagePattern );
	} ).map( resourceName => {
		const data = {
			page: getTransactionsResourcePage( resourceName ),
			per_page: getTransactionsResourcePerPage( resourceName ),
		};
		const url = `${ NAMESPACE }/payments/transactions`;

		return fetch( { path: url, data: data } )
			.then( dataToResources )
			.catch( error => {
				return {
					[ `transactions-list-page-${ data.page }-perpage-${ data.per_page }` ]: {
						error,
					},
				};
			} );
	} );

	return resources;
}

export function transactionsPageToResources( transactions ) {
	const page = transactions.summary.page;
	const per_page = transactions.summary.per_page;

	return {
		[ `transactions-list-page-${ page }-perpage-${ per_page }` ]: {
			data: transactions,
		},
	};
}

export function transactionsToResources( transactions ) {
	return {
		[ 'transactions-list' ]: {
			data: transactions,
		},
	};
}

export default {
	read,
};
