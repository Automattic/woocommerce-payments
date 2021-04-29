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

const defaultState = { byId: {}, queries: {}, count: 0, instant: {} };

const receiveDeposits = (
	state = defaultState,
	{ type, query = {}, data = [], error }
) => {
	const index = getResourceId( query );

	switch ( type ) {
		case TYPES.SET_DEPOSIT:
			return {
				...state,
				byId: { ...state.byId, [ data.id ]: data },
			};
		case TYPES.SET_DEPOSITS_OVERVIEW:
			return {
				...state,
				overview: {
					...state.overview,
					data,
				},
			};
		case TYPES.SET_ERROR_FOR_DEPOSITS_OVERVIEW:
			return {
				...state,
				overview: {
					...state.overview,
					error,
				},
			};
		case TYPES.SET_DEPOSITS:
			return {
				...state,
				byId: { ...state.byId, ...keyBy( data, 'id' ) },
				queries: {
					...state.queries,
					[ index ]: {
						data: map( data, 'id' ),
					},
				},
			};
		// Note: count is currently independent of query, so no need to map from the query index as above.
		case TYPES.SET_DEPOSITS_COUNT:
			return {
				...state,
				count: data,
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
		case TYPES.SET_DEPOSITS_SUMMARY:
			return {
				...state,
				summary: {
					...state.summary,
					[ index ]: {
						data: data,
					},
				},
			};
		case TYPES.SET_ERROR_FOR_DEPOSITS_SUMMARY:
			return {
				...state,
				summary: {
					...state.summary,
					[ index ]: {
						error: error,
					},
				},
			};
		case TYPES.SET_INSTANT_DEPOSIT:
			return {
				...state,
				instant: {
					...state.instant,
					data,
				},
			};
	}

	return state;
};

export default receiveDeposits;
