/** @format */

/**
 * Internal dependencies
 */
import { Country, OnboardingState, State } from './types';

export const getOnboardingState = ( state: State ): OnboardingState => {
	if ( ! state ) {
		return {};
	}

	return state.onboarding || {};
};

export const getBusinessTypes = ( state: State ): Country[] => {
	return getOnboardingState( state ).data || [];
};
