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
import {
	updateErrorForLatestFraudOutcome,
	updateLatestFraudOutcome,
} from './actions';
import { FraudOutcome } from '../../types/fraud-outcome';

export function* getLatestFraudOutcome( id: string ): Generator< unknown > {
	try {
		const result = yield apiFetch( {
			path: `${ NAMESPACE }/fraud_outcomes/${ id }/latest`,
		} );
		yield updateLatestFraudOutcome( id, result as FraudOutcome );
	} catch ( e ) {
		yield updateErrorForLatestFraudOutcome( id, e as ApiError );
	}
}
