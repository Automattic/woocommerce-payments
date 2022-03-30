/** @format */

/**
 * Internal dependencies
 */
import ACTION_TYPES from './action-types';
import { UpdateBusinessTypesAction, OnboardingState } from './types';

const defaultState: OnboardingState = { countries: [] };

export default (
	state = defaultState,
	action: UpdateBusinessTypesAction
): OnboardingState => {
	switch ( action.type ) {
		case ACTION_TYPES.SET_BUSINESS_TYPES:
			return {
				...state,
				countries: action.data,
			};
		default:
			return state;
	}
};
