/** @format */

/**
 * External dependencies
 */
import { Reducer } from 'redux';
import { combineReducers } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { Timeline } from './types';
import { TimelineAction } from './actions';

const data: Reducer< Record< string, Timeline >, TimelineAction > = (
	state = {},
	action
) => {
	switch ( action.type ) {
		case 'SET_TIMELINE':
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

const errors: Reducer< Record< string, Error >, TimelineAction > = (
	state = {},
	action
) => {
	switch ( action.type ) {
		case 'SET_ERROR_FOR_TIMELINE':
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

export type TimelineState = ReturnType< typeof reducer >;

export default reducer;
