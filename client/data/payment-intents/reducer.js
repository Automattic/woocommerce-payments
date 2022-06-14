/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';

const receivePaymentIntent = ( state = {}, { type, id, data, error } ) => {
	switch ( type ) {
		case TYPES.SET_PAYMENT_INTENT:
			state = {
				...state,
				[ id ]: {
					...state[ id ],
					data,
				},
			};
			break;
		case TYPES.SET_ERROR_FOR_PAYMENT_INTENT:
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

export default receivePaymentIntent;
