/** @format */

/**
 * Internal dependencies
 */
import { ApiError } from '../../types/errors';
import { State } from '../types';
import { Order } from './types';

const getOrdersState = ( { orders }: State, id: string ) => {
	return orders?.[ id ] || {};
};

export const getOrder = ( state: State, id: string ): Order | undefined =>
	getOrdersState( state, id )?.data;

export const getOrderError = (
	state: State,
	id: string
): ApiError | undefined => getOrdersState( state, id )?.error;
