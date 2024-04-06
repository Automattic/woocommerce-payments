/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';

const receivePaymentActivity = ( state = {}, { type, id, data, error } ) => {
	switch ( type ) {
		case TYPES.SET_PAYMENT_ACTIVITY_DATA:
			state = {
				...state,
				paymentActivityData: data,
			};
			break;
	}
	return state;
};

export default receivePaymentActivity;
