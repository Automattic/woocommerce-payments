/** @format */

/**
 * Internal Dependencies
 */
import { ApiError } from '../../types/errors';
import { FraudOutcome } from '../../types/fraud-outcome';
import ACTION_TYPES from './action-types';

export interface UpdateLatestFraudOutcomeAction {
	type: ACTION_TYPES.SET_LATEST_FRAUD_OUTCOME;
	id: string;
	data: FraudOutcome;
}

export interface UpdateErrorForLatestFraudOutcomeAction {
	type: ACTION_TYPES.SET_ERROR_FOR_LATEST_FRAUD_OUTCOME;
	id: string;
	error: ApiError;
}

export interface LatestFraudOutcomeState {
	data?: FraudOutcome;
	error?: ApiError;
}

export interface FraudOutcomesState {
	latestFraudOutcome: Record< string, LatestFraudOutcomeState >;
}

export type FraudOutcomesActions =
	| UpdateLatestFraudOutcomeAction
	| UpdateErrorForLatestFraudOutcomeAction;

export interface LatestFraudOutcomeResponse {
	data?: FraudOutcome;
	error?: ApiError;
	isLoading: boolean;
}
