/** @format */

/**
 * External dependencies
 */
import { Reducer } from 'redux';
import { combineReducers } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { ChargeAction } from './actions';
import { WCPayCharge } from './types';

export const data: Reducer< Record< string, WCPayCharge >, ChargeAction > = (
	state = {},
	action
) => {
	switch ( action.type ) {
		case 'SET_CHARGE':
			return {
				...state,
				[ action.id ]: {
					...state[ action.id ],
					...action.data,
				},
			};
	}
	return state;
};

export const errors: Reducer< Record< string, Error >, ChargeAction > = (
	state = {},
	action
) => {
	switch ( action.type ) {
		case 'SET_CHARGE':
			// Clear error state when charge is updated.
			delete state[ action.id ];
			return state;
		case 'SET_ERROR_FOR_CHARGE':
			return {
				...state,
				[ action.id ]: action.error,
			};
	}
	return state;
};

const reducer = combineReducers( {
	data,
	errors,
} );

export type ChargeState = ReturnType< typeof reducer >;

export default reducer;
