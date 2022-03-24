/** @format */

/**
 * Internal Dependencies
 */
import ACTION_TYPES from './action-types';

export interface Country {
	key: string;
	name: string;
	types: BusinessType[];
}

export interface BusinessType {
	key: string;
	name: string;
	structures: BusinessStructure[];
}

export interface BusinessStructure {
	key: string;
	name: string;
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
