/** @format */

/**
 * Internal Dependencies
 */
import { ApiError } from '../../types/errors';
import { FraudOutcome } from '../../types/fraud-outcome';
import TYPES from './action-types';
import {
	UpdateErrorForLatestFraudOutcomeAction,
	UpdateLatestFraudOutcomeAction,
} from './types';

export function updateLatestFraudOutcome(
	id: string,
	data: FraudOutcome
): UpdateLatestFraudOutcomeAction {
	return {
		type: TYPES.SET_LATEST_FRAUD_OUTCOME,
		id,
		data,
	};
}

export function updateErrorForLatestFraudOutcome(
	id: string,
	error: ApiError
): UpdateErrorForLatestFraudOutcomeAction {
	return {
		type: TYPES.SET_ERROR_FOR_LATEST_FRAUD_OUTCOME,
		id,
		error,
	};
}
