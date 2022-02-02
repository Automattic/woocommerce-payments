/** @format */

/**
 * External dependencies
 */
import { map, keyBy } from 'lodash';

/**
 * Internal dependencies
 */
import TYPES from './action-types';
import { getResourceId } from 'utils/data';

const defaultState = { byId: {}, queries: {}, summary: {}, cached: {} };

const receiveDisputes = (
	state = defaultState,
	{ type, query = {}, data = [] }
) => {
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
				cached: { ...state.cached, ...keyBy( data, 'dispute_id' ) },
				queries: {
					...state.queries,
					[ index ]: {
						data: map( data, 'dispute_id' ),
					},
				},
			};
		case TYPES.SET_DISPUTES_SUMMARY:
			return {
				...state,
				summary: {
					...state.summary,
					[ index ]: {
						data: data,
					},
				},
			};
	}

	return state;
};

export default receiveDisputes;
