/** @format */

/**
 * Internal dependencies
 */
import ACTION_TYPES from './action-types';
import {
	PromotionsData,
	UpdatePromotionsAction,
	ErrorPromotionsAction,
} from './types';
import { ApiError } from '../../types/errors';

export function updatePromotions(
	data: PromotionsData
): UpdatePromotionsAction {
	return {
		type: ACTION_TYPES.SET_PROMOTIONS,
		data,
	};
}

export function updateErrorForPromotions(
	error: ApiError
): ErrorPromotionsAction {
	return {
		type: ACTION_TYPES.SET_ERROR_FOR_PROMOTIONS,
		error,
	};
}
