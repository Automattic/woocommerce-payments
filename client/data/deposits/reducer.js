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

const defaultState = { byId: {}, queries: {}, instant: {}, page: false };

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
		case TYPES.SET_INSTANT_DEPOSIT:
			return {
				...state,
				instant: {
					...state.instant,
					data,
				},
			};
		case TYPES.SET_DEPOSITS_PAGE:
			return {
				...state,
				page: {
					...state.page,
					data,
				},
			};
	}

	return state;
};

export default receiveDeposits;
