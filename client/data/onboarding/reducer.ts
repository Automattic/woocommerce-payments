/** @format */

/**
 * Internal dependencies
 */
import {
	INIT_EMBEDDED_ONBOARDING,
	INITIAL_STATE,
	OnboardingState,
} from './types';

export const reducer = (
	state: OnboardingState = INITIAL_STATE,
	action: any
): OnboardingState => {
	switch ( action.type ) {
		case INIT_EMBEDDED_ONBOARDING:
			return {
				...state,
				accountSession: action.accountSession,
			};
		default:
			return state;
	}
};
