/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';
import {
	OrdersActions,
	OrdersState,
	UpdateErrorForOrderAction,
	UpdateOrderAction,
} from './types';

const defaultState = {};

const receiveOrders = (
	state: OrdersState = defaultState,
	action: OrdersActions
): OrdersState => {
	const { type, id } = action;

	switch ( type ) {
		case TYPES.SET_ORDER:
			return {
				...state,
				[ id ]: {
					...state[ id ],
					data: ( action as UpdateOrderAction ).data,
					error: undefined,
				},
			};
		case TYPES.SET_ERROR_FOR_ORDER:
			return {
				...state,
				[ id ]: {
					...state[ id ],
					data: undefined,
					error: ( action as UpdateErrorForOrderAction ).error,
				},
			};
		default:
			return state;
	}
};

export default receiveOrders;
