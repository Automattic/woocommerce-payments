/** @format */

/**
 * Internal dependencies
 */
import ACTION_TYPES from './action-types';
import { UpdateBusinessTypesAction, OnboardingState } from './types';

const defaultState = {};

export default (
	state: OnboardingState = defaultState,
	action: UpdateBusinessTypesAction
): OnboardingState => {
	switch ( action.type ) {
		case ACTION_TYPES.SET_BUSINESS_TYPES:
			return {
				...state,
				data: action.data,
			};
	}

	return state;
};
