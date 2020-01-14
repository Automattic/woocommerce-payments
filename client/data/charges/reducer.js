/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';

const receiveCharges = ( state = {}, { type, id, data, error } ) => {
	switch ( type ) {
		case TYPES.SET_CHARGE:
			return {
				...state,
				[ id ]: data,
			};
		case TYPES.SET_ERROR_FOR_CHARGE:
			return {
				...state,
				errors: {
					...state.errors,
					[ id ]: error,
				},
			};
	}
	return state;
};

export default receiveCharges;
