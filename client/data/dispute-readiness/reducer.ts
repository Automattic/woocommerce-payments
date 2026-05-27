/** @format */

/**
 * Internal dependencies
 */
import { ACTION_TYPES } from './action-types';
import { DisputeReadinessAction, DisputeReadinessState } from './types';

const defaultState: DisputeReadinessState = {
	disputeReadiness: undefined,
	disputeReadinessError: undefined,
};

export default function disputeReadiness(
	state: DisputeReadinessState = defaultState,
	action: DisputeReadinessAction
): DisputeReadinessState {
	switch ( action.type ) {
		case ACTION_TYPES.SET_DISPUTE_READINESS:
			return {
				...state,
				disputeReadiness: action.data,
				disputeReadinessError: undefined,
			};
		case ACTION_TYPES.SET_ERROR_FOR_DISPUTE_READINESS:
			return {
				...state,
				disputeReadinessError: action.error,
			};
	}

	return state;
}
