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
