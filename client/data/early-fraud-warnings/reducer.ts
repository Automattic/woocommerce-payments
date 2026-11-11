/** @format */

/**
 * Internal dependencies
 */
import { ACTION_TYPES } from './action-types';
import { EarlyFraudWarningsAction, EarlyFraudWarningsState } from './types';

const defaultState: EarlyFraudWarningsState = {
	activeEarlyFraudWarnings: undefined,
	activeEarlyFraudWarningsError: undefined,
};

export default function earlyFraudWarnings(
	state: EarlyFraudWarningsState = defaultState,
	action: EarlyFraudWarningsAction
): EarlyFraudWarningsState {
	switch ( action.type ) {
		case ACTION_TYPES.SET_ACTIVE_EARLY_FRAUD_WARNINGS:
			return {
				...state,
				activeEarlyFraudWarnings: action.data,
				activeEarlyFraudWarningsError: undefined,
			};
		case ACTION_TYPES.SET_ERROR_FOR_ACTIVE_EARLY_FRAUD_WARNINGS:
			return {
				...state,
				activeEarlyFraudWarningsError: action.error,
			};
	}

	return state;
}
