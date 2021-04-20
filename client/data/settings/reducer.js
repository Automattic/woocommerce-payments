/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';

const defaultState = { byId: {}, queries: {}, count: 0 };

const receiveSettings = (
	state = defaultState,
	{ type, query = {}, data = [], error }
) => {
	switch ( type ) {
		case TYPES.SET_SETTINGS:
			return {
				...state,
				overview: {
					...state.overview,
					data,
					error,
				},
			};
	}

	return state;
};

export default receiveSettings;
