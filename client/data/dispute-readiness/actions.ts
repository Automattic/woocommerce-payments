/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { ACTION_TYPES } from './action-types';
import { NAMESPACE } from '../constants';
import { ApiError } from '../../types/errors';
import {
	DisputeReadinessData,
	ErrorDisputeReadinessAction,
	UpdateDisputeReadinessAction,
} from './types';

export const updateDisputeReadiness = (
	data: DisputeReadinessData
): UpdateDisputeReadinessAction => ( {
	type: ACTION_TYPES.SET_DISPUTE_READINESS,
	data,
} );

export const updateErrorForDisputeReadiness = (
	error: ApiError
): ErrorDisputeReadinessAction => ( {
	type: ACTION_TYPES.SET_ERROR_FOR_DISPUTE_READINESS,
	error,
} );

export function* dismissDisputeReadinessCard(): unknown {
	try {
		const result = yield apiFetch( {
			path: `${ NAMESPACE }/dispute-readiness/dismiss`,
			method: 'POST',
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

export function* confirmStatementDescriptor(): unknown {
	try {
		const result = yield apiFetch( {
			path: `${ NAMESPACE }/dispute-readiness/statement-descriptor/confirm`,
			method: 'POST',
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
