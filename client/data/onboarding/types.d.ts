/** @format */

/**
 * Internal Dependencies
 */
import ACTION_TYPES from './action-types';

export interface Country {
	[ country_code: string ]: BusinessType[];
}

export interface BusinessType {
	[ structure: string ]: BusinessStructure[];
}

export interface BusinessStructure {
	key: string;
	label: string;
}

export interface UpdateBusinessTypesAction {
	type: ACTION_TYPES.SET_BUSINESS_TYPES;
	data: Country[];
}

export interface OnboardingState {
	data?: Country[];
}

export interface State {
	onboarding?: OnboardingState;
}
