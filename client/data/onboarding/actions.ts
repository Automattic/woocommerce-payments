/** @format */

/**
 * Internal Dependencies
 */
import ACTION_TYPES from './action-types';
import { Country, UpdateBusinessTypesAction } from './types';

export function updateBusinessTypes(
	data: Country[]
): UpdateBusinessTypesAction {
	return {
		type: ACTION_TYPES.SET_BUSINESS_TYPES,
		data,
	};
}
