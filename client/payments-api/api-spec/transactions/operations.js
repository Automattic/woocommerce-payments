/** @format */

/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { compact, concat, includes, startsWith } from 'lodash';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../../constants';

function read( resourceNames, fetch = apiFetch, dataToResources = transactionsToResources ) {
	return concat(
		readTransaction( resourceNames, fetch ),
		readTransactions( resourceNames, fetch, dataToResources ),
	);
}

export function readTransaction( resourceNames, fetch, dataToResources = transactionToResources ) {
	return compact(
		resourceNames.map( resourceName => {
			if ( startsWith( resourceName, 'txn_' ) ) {
				const url = `${ NAMESPACE }/payments/transactions/${ resourceName }`;

				return fetch( { path: url } )
						.then( dataToResources )
						.catch( error => {
							return { [ resourceName ]: { data: error } };
						} );
			}
		} )
	);
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

export function transactionsToResources( transactions ) {
	const resources = {	[ 'transactions-list' ]: { data: transactions } };
	transactions.data.forEach( transaction => {
		resources[ transaction.id ] = { data: transaction };
	} );
	return resources;
}

export function transactionToResources( transaction ) {
	return { [ transaction.id ]: { data: transaction } };
}

export default {
	read,
};
