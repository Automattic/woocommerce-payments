/** @format */

/**
 * Internal Dependencies
 */
import { ApiError } from '../../types/errors';
import ACTION_TYPES from './action-types';

interface Order {
	id: number;
	currency: string;
	total: string;
	billing: {
		first_name: string;
		last_name: string;
		email: string;
		address_1: string;
		address_2: string;
		country: string;
		phone: string;
		state: string;
		postcode: string;
		city: string;
	};
	date_created: {
		date: string;
	};
	status: string;
	payment_intent_id?: string;
}

export interface UpdateOrderAction {
	type: ACTION_TYPES.SET_ORDER;
	id: string;
	data: Order;
}

export interface UpdateErrorForOrderAction {
	type: ACTION_TYPES.SET_ERROR_FOR_ORDER;
	id: string;
	error: ApiError;
}

export type OrdersState = Record< string, { data?: Order; error?: ApiError } >;

export type OrdersActions = UpdateOrderAction | UpdateErrorForOrderAction;

export interface OrderResponse {
	data?: Order;
	error?: ApiError;
	isLoading: boolean;
}
