/** @format */

/**
 * External dependencies
 */
import { map, keyBy } from 'lodash';

/**
 * Internal dependencies
 */
import TYPES from './action-types';
import { getResourceId } from '../util';

const defaultState = { byId: {}, queries: {} };

const receiveDeposits = ( state = defaultState, { type, query = {}, data = [], error } ) => {
	const index = getResourceId( query );

	switch ( type ) {
		case TYPES.SET_DEPOSITS:
			return {
				...state,
				byId: { ...state.byId, ...( keyBy( data, 'id' ) ) },
				queries: {
					...state.queries,
					[ index ]: {
						data: map( data, 'id' ),
					},
				},
			};
		case TYPES.SET_ERROR_FOR_DEPOSIT_QUERY:
			return {
				...state,
				queries: {
					...state.queries,
					[ index ]: {
						error: error,
					},
				},
			};
	}

	return state;
};

export default receiveDeposits;
