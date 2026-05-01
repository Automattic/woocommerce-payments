/** @format */

/**
 * Internal dependencies
 */
import { ACTION_TYPES } from './action-types';

const defaultState = {
	disputeReadiness: undefined,
	disputeReadinessError: undefined,
};

export default function disputeReadiness( state = defaultState, action ) {
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
