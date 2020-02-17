/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';

const receiveCharges = ( state = {}, { type, id, data, error } ) => {
	switch ( type ) {
		case TYPES.SET_CHARGE:
			state = {
				...state,
				[ id ]: {
					...state[ id ],
					data,
				},
			};
			break;
		case TYPES.SET_ERROR_FOR_CHARGE:
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

export default receiveCharges;
