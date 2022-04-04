/** @format */

/**
 * Internal dependencies
 */
import ACTION_TYPES from './action-types';
import {
	UpdateBusinessTypesAction,
	OnboardingState,
	UpdateRequiredVerificationInfoAction,
} from './types';

const defaultState: OnboardingState = { countries: [] };

export default (
	state = defaultState,
	action: UpdateBusinessTypesAction | UpdateRequiredVerificationInfoAction
): OnboardingState => {
	switch ( action.type ) {
		case ACTION_TYPES.SET_BUSINESS_TYPES:
			return {
				...state,
				countries: action.data,
			};
		case ACTION_TYPES.SET_REQUIRED_VERIFICATION_INFO:
			return {
				...state,
				requiredFields: action.data,
			};
		default:
			return state;
	}
};
