/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import { DISPUTE_READINESS_STORE_NAME as STORE_NAME } from '../store-names';

export function* getDisputeReadiness(): unknown {
	try {
		const result = yield apiFetch( {
			path: `${ NAMESPACE }/dispute-readiness`,
		} );

		yield controls.dispatch( STORE_NAME, 'updateDisputeReadiness', result );
	} catch ( error ) {
		yield controls.dispatch(
			STORE_NAME,
			'updateErrorForDisputeReadiness',
			error
		);
	}
}
