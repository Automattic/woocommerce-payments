/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import { ApiError } from '../../types/errors';
import { updateErrorForOrder, updateOrder } from './actions';
import { Order } from './types';

export function* getOrder( id: string ): Generator< unknown > {
	try {
		const result = yield apiFetch( {
			path: `${ NAMESPACE }/orders/${ id }`,
		} );
		yield updateOrder( id, result as Order );
	} catch ( e ) {
		yield updateErrorForOrder( id, e as ApiError );
	}
}
