/** @format */

/**
 * External dependencies
 */
import { Reducer } from 'redux';

/**
 * Internal dependencies
 */
import { ChargeAction } from './actions';
import { ChargesStateEntry } from './types';

export const receiveCharges: Reducer<
	Record< string, ChargesStateEntry >,
	ChargeAction
> = ( state = {}, action ) => {
	switch ( action.type ) {
		case 'SET_CHARGE':
			state = {
				...state,
				[ action.id ]: {
					...state[ action.id ],
					data: action.data,
				},
			};
			break;
		case 'SET_ERROR_FOR_CHARGE':
			state = {
				...state,
				[ action.id ]: {
					...state[ action.id ],
					error: action.error,
				},
			};
			break;
	}
	return state;
};

export type ChargeState = ReturnType< typeof receiveCharges >;

export default receiveCharges;
