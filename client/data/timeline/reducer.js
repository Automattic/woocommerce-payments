/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';

const receiveTimeline = ( state = {}, { type, id, data, error } ) => {
	switch ( type ) {
		case TYPES.SET_TIMELINE:
			state = {
				...state,
				[ id ]: {
					data,
				},
			};
			break;
		case TYPES.SET_ERROR_FOR_TIMELINE:
			state = {
				...state,
				[ id ]: {
					...state[ id ],
					error,
				},
			};
			break;
	}
	return state;
};

export default receiveTimeline;
