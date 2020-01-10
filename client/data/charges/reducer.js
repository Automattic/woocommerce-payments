/**
 * @format
 */

/**
 * Internal dependencies
 */
import TYPES from './action-types';

const receiveCharges = ( state = {}, { type, id, data, error } ) => {
	const newState = { charges: { ...state.charges } };
	switch ( type ) {
		case TYPES.UPDATE_CHARGE:
			newState.charges[ id ] = data;
			state = newState;
			break;
		case TYPES.UPDATE_ERROR_FOR_CHARGE:
			newState.charges[ id ] = error;
			state = newState;
			break;
	}
	return state;
};

export default receiveCharges;
