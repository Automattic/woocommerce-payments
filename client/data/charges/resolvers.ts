/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import { updateCharge, updateErrorForCharge } from './actions';
import { WCPayCharge } from './types';

const isCharge = ( apiResponse: any ): apiResponse is WCPayCharge => {
	return 'charge' === ( apiResponse as WCPayCharge ).object;
};

export function* getCharge( id: string ) {
	try {
		const results = yield apiFetch( {
			path: `${ NAMESPACE }/charges/${ id }`,
		} );

		if ( isCharge( results ) ) {
			yield updateCharge( id, results );
		} else {
			throw new Error( 'Received invalid Charge object from WCPay API' );
		}
	} catch ( e ) {
		yield updateErrorForCharge( id, e );
	}
}
