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
	const prefix = 'transactions-list-page-perpage';
	const resources = resourceNames.filter( resourceName => {
		// Only process requests for transactions page resources.
		return isResourcePrefix( resourceName, prefix );
	} ).map( resourceName => {
		const data = getResourceIdentifier( resourceName );
		const url = `${ NAMESPACE }/payments/transactions`;

		return fetch( { path: url, data: data } )
			.then( dataToResources )
			.catch( error => {
				return {
					[ getResourceName( prefix, data ) ]: {
						error,
					},
				};
			} );
	} );

	return resources;
}

export function transactionsPageToResources( transactions ) {
	const identifier = {
		page: transactions.summary.page,
		per_page: transactions.summary.per_page,
	};

	return {
		[ getResourceName( 'transactions-list-page-perpage', identifier ) ]: {
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
