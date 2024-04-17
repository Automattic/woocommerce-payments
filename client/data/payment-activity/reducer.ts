/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';
import { PaymentActivityAction, PaymentActivityState } from './types';

const receivePaymentActivity = (
	state: PaymentActivityState = {},
	{ type, data }: PaymentActivityAction
): PaymentActivityState => {
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
