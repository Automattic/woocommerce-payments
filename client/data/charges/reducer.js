/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';

const receiveCharges = ( state = {}, { type, id, data, error } ) => {
	const newState = { charges: { ...state.charges } };
	switch ( type ) {
		case TYPES.SET_CHARGE:
			newState.charges[ id ] = data;
			break;
		case TYPES.SET_ERROR_FOR_CHARGE:
			newState.charges[ id ] = error;
			break;
	}
	return newState;
};

export default receiveCharges;
