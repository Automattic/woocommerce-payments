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
import { EARLY_FRAUD_WARNINGS_STORE_NAME as STORE_NAME } from '../store-names';

export function* getActiveEarlyFraudWarnings(): unknown {
	try {
		const result = yield apiFetch( {
			path: `${ NAMESPACE }/early_fraud_warnings/active`,
		} );

		yield controls.dispatch(
			STORE_NAME,
			'updateActiveEarlyFraudWarnings',
			result
		);
	} catch ( error ) {
		yield controls.dispatch(
			STORE_NAME,
			'updateErrorForActiveEarlyFraudWarnings',
			error
		);
	}
}
