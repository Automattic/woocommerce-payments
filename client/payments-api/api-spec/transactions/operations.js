/** @format */

/**
 * External dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import { includes } from 'lodash';

/**
 * Internal dependencies.
 */
import { NAMESPACE } from '../../constants';

function read( resourceNames, fetch = apiFetch ) {
	return readTransactions( resourceNames, fetch );
}

function readTransactions( resourceNames, fetch ) {
	const resourceName = 'transactions-list';

	if ( includes( resourceNames, resourceName ) ) {
		const url = `${ NAMESPACE }/payments/transactions`;

		return [
			fetch( { path: url } )
				.then( transactionsToResources )
				.catch( error => {
					return { [ resourceName ]: { error } };
				} )
		];
	}

	return [];
}

function transactionsToResources( transactions ) {
	return {
		[ 'transactions-list' ]: {
			data: transactions,
		}
	};
}

export default {
	read,
};
