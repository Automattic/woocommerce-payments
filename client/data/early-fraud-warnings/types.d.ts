/** @format */

/**
 * Internal dependencies
 */
import { ApiError } from '../../types/errors';
import { ACTION_TYPES } from './action-types';

/**
 * A payment whose latest early fraud warning is still actionable.
 */
export interface ActiveEarlyFraudWarning {
	order_id: number;
	charge_id: string;
	created: number;
}

export interface EarlyFraudWarningsState {
	activeEarlyFraudWarnings?: ActiveEarlyFraudWarning[];
	activeEarlyFraudWarningsError?: ApiError;
}

export interface UpdateActiveEarlyFraudWarningsAction {
	type: ACTION_TYPES.SET_ACTIVE_EARLY_FRAUD_WARNINGS;
	data: ActiveEarlyFraudWarning[];
}

export interface ErrorActiveEarlyFraudWarningsAction {
	type: ACTION_TYPES.SET_ERROR_FOR_ACTIVE_EARLY_FRAUD_WARNINGS;
	error: ApiError;
}

export type EarlyFraudWarningsAction =
	| UpdateActiveEarlyFraudWarningsAction
	| ErrorActiveEarlyFraudWarningsAction;

export interface ActiveEarlyFraudWarningsResponse {
	activeEarlyFraudWarnings: ActiveEarlyFraudWarning[];
	activeEarlyFraudWarningsError?: ApiError;
	/**
	 * True once the request has settled, either with data or with an error.
	 *
	 * `isResolving` is false on the very first render, before the resolver starts, so
	 * callers that gate on it alone briefly treat "not asked yet" as "nothing to show".
	 */
	hasLoaded: boolean;
}
