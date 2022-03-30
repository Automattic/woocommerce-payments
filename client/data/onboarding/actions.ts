/** @format */

/**
 * Internal Dependencies
 */
import ACTION_TYPES from './action-types';
import {
	Country,
	UpdateRequiredVerificationInfoAction,
	UpdateBusinessTypesAction,
} from './types';

export function updateBusinessTypes(
	data: Country[]
): UpdateBusinessTypesAction {
	return {
		type: ACTION_TYPES.SET_BUSINESS_TYPES,
		data,
	};
}

export function updateRequiredVerificationInfo(
	data: string[]
): UpdateRequiredVerificationInfoAction {
	return {
		type: ACTION_TYPES.SET_REQUIRED_VERIFICATION_INFO,
		data,
	};
}
