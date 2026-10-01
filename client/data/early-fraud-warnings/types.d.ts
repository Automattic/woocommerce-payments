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
	/**
	 * Display order number from `WC_Order::get_order_number()`.
	 * Absent on a stale cache entry from before this field shipped.
	 */
	order_number?: string;
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
	 * True once a list has been loaded, or the first request has settled with an error.
	 *
	 * Stays true while a later refetch is in flight, so callers keep showing the previous
	 * list rather than blanking it. `isResolving` would not do: it is false on the very
	 * first render, before the resolver starts, and would read "not asked yet" as "nothing".
	 */
	hasLoaded: boolean;
}
