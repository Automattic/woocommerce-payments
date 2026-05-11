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

export function* getDisputeReadiness(): unknown {
	try {
		const result = yield apiFetch( {
			path: `${ NAMESPACE }/dispute-readiness`,
		} );

		yield controls.dispatch(
			'wc/payments',
			'updateDisputeReadiness',
			result
		);
	} catch ( error ) {
		yield controls.dispatch(
			'wc/payments',
			'updateErrorForDisputeReadiness',
			error
		);
	}
}
