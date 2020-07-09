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

const receiveDisputes = ( state = defaultState, { type, query = {}, data = [] } ) => {
	const index = getResourceId( query );

	switch ( type ) {
		case TYPES.SET_DISPUTE:
			return {
				...state,
				byId: { ...state.byId, [ data.id ]: data },
			};
		case TYPES.SET_DISPUTES:
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
	}

	return state;
};

export default receiveDisputes;
