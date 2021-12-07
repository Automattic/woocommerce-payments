/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';

const receiveCharges = ( state = {}, { type, id, data, error } ) => {
	switch ( type ) {
		case TYPES.SET_CARD_READER_STATS:
			state = {
				...state,
				[ id ]: {
					...state[ id ],
					data,
				},
			};
			break;
		case TYPES.SET_ERROR_FOR_CARD_READER_STATS:
			state = {
				...state,
				[ id ]: {
					...state[ id ],
					error,
				},
			};
			break;
		case TYPES.SET_READERS:
			state = {
				...state,
				list: data,
			};

			break;
	}
	return state;
};

export default receiveCharges;
