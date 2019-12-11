/** @format */

/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { compact, startsWith } from 'lodash';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../../constants';

function read( resourceNames, fetch = apiFetch, dataToResources = chargeToResources ) {
	return readCharge( resourceNames, fetch, dataToResources );
}

export function readCharge( resourceNames, fetch, dataToResources ) {
	return compact(
		resourceNames.map( resourceName => {
			if ( startsWith( resourceName, 'ch_' ) ) {
				const url = `${ NAMESPACE }/payments/charges/${ resourceName }`;

				return fetch( { path: url } )
						.then( dataToResources )
						.catch( error => {
							return { [ resourceName ]: { data: error } };
						} );
			}
		} )
	);
}

export function chargeToResources( charge ) {
	return { [ charge.id ]: { data: charge } };
}

export default {
	read,
};
