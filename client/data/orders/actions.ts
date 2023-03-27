/** @format */

/**
 * Internal Dependencies
 */
import { ApiError } from '../../types/errors';
import TYPES from './action-types';
import { Order, UpdateErrorForOrderAction, UpdateOrderAction } from './types';

export function updateOrder( id: string, data: Order ): UpdateOrderAction {
	return {
		type: TYPES.SET_ORDER,
		id,
		data,
	};
}

export function updateErrorForOrder(
	id: string,
	error: ApiError
): UpdateErrorForOrderAction {
	return {
		type: TYPES.SET_ERROR_FOR_ORDER,
		id,
		error,
	};
}
